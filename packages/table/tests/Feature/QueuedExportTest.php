<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Export\Contracts\Exporter;
use NyonCode\WireTable\Export\ExcelExporter;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Export\Jobs\RunExportJob;
use NyonCode\WireTable\Export\TableExport;
use NyonCode\WireTable\Table;

/*
 * An export that finishes after the request that asked for it.
 *
 * A download is a response and a job has none to return, so a queued export is a
 * different *delivery*, not the same one moved. What must not differ is the
 * export itself: the same config, the same filtered query, the same visible
 * columns — an export that chose different columns depending on how it was
 * delivered would be a bug nobody sees until they compare two files.
 */
class QeRow extends Model
{
    protected $table = 'qe_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class QeHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(QeRow::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('secret')->visible(false),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('qe_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('secret');
    });

    QeRow::insert([
        ['id' => 1, 'name' => 'Ada', 'secret' => 'hidden-1'],
        ['id' => 2, 'name' => 'Grace', 'secret' => 'hidden-2'],
    ]);

    Storage::fake('local');
});

afterEach(function () {
    Schema::dropIfExists('qe_rows');
});

// ─── Writing to a path is the one implementation ─────────────────────────────

it('writes the same rows to a file that it would have streamed', function () {
    // php://output is a path like any other, so the download is this method in a
    // response wrapper. Two copies of "turn records into a file" would drift the
    // moment one of them learned about a column type.
    $host = new QeHost;
    $host->mountWithTable();

    [$export, $query, $columns] = $host->buildTableExport(ExportFormat::Csv);
    $path = $export->store($query, $columns, 'local');

    $csv = Storage::disk('local')->get($path);

    expect($csv)->toContain('Ada')
        ->toContain('Grace')
        // A column the viewer cannot see is not in the file either.
        ->not->toContain('hidden-1');
});

it('puts the file where it was told, under the configured name', function () {
    $host = new QeHost;
    $host->mountWithTable();

    [$export, $query, $columns] = $host->buildTableExport(ExportFormat::Csv);

    $path = $export->store($query, $columns, 'local', 'reports/monthly');

    expect($path)->toStartWith('reports/monthly/')
        ->and($path)->toEndWith('.csv')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

// ─── The queued delivery ─────────────────────────────────────────────────────

it('dispatches instead of streaming, and says it is coming', function () {
    Queue::fake();

    Livewire::test(QeHost::class)
        ->call('queueTableExport', 'csv')
        ->assertDispatched('table-notification');

    Queue::assertPushed(RunExportJob::class, function (RunExportJob $job): bool {
        return $job->host === QeHost::class && $job->format === 'csv';
    });
});

it('carries a class and a format, not a query', function () {
    // A query is closures and a builder; neither survives serialization, and an
    // export of ten thousand rows serialized into a payload would be the thing
    // it was meant to avoid.
    $job = new RunExportJob(QeHost::class, 'csv');

    expect(unserialize(serialize($job)))->toBeInstanceOf(RunExportJob::class);
});

it('exports the data as it is when the job runs', function () {
    // The host is rebuilt inside the job, so a row added after dispatch is in
    // the file — which is the point of carrying a class rather than a result.
    $job = new RunExportJob(QeHost::class, 'csv', 'local');

    QeRow::insert([['id' => 3, 'name' => 'Alan', 'secret' => 'hidden-3']]);

    $job->handle();

    $files = Storage::disk('local')->files('exports');

    expect($files)->toHaveCount(1)
        ->and(Storage::disk('local')->get($files[0]))->toContain('Alan');
});

it('says where the file is once it is written', function () {
    // By the time a large export finishes there is no request left to flash
    // into, which is why the database driver exists.
    $sent = [];

    NotificationManager::setDefaultDriver(new class($sent) implements NotificationDriver
    {
        public function __construct(public array &$sent) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification->message;
        }
    });

    (new RunExportJob(QeHost::class, 'csv', 'local'))->handle();

    expect($sent)->toHaveCount(1)
        ->and($sent[0])->toContain('.csv');

    NotificationManager::reset();
});

it('does nothing for a host that cannot build an export', function () {
    // Not every component is a table host; the job answers rather than fataling.
    (new RunExportJob(QeRow::class, 'csv', 'local'))->handle();

    expect(Storage::disk('local')->files('exports'))->toBe([]);
});

it('exports what the user filtered, not the whole table', function () {
    // The one that matters. Without the state travelling, the worker mounts
    // fresh: someone who narrowed a table to one row and queued the export
    // receives every row, in a file plausible enough that nobody checks.
    Queue::fake();

    $test = Livewire::test(QeHost::class)
        ->set('tableState.search', 'Ada')
        ->call('queueTableExport', 'csv', 'local');

    $job = null;
    Queue::assertPushed(RunExportJob::class, function (RunExportJob $pushed) use (&$job): bool {
        $job = $pushed;

        return true;
    });

    $job->handle();

    $csv = Storage::disk('local')->get(Storage::disk('local')->files('exports')[0]);

    expect($csv)->toContain('Ada')
        ->not->toContain('Grace');
});

it('names the file after what it actually wrote, not what was asked for', function () {
    // The download path renames an .xlsx to .csv when PhpSpreadsheet is absent —
    // "the reader has to be told what they actually got". A stored file has no
    // response to carry that in, so an .xlsx holding CSV is exactly the defect
    // the sync path avoids, and nobody opens it until much later.
    $export = (new class extends TableExport
    {
        protected function resolveExporter(): Exporter
        {
            return new class extends ExcelExporter
            {
                public static function isAvailable(): bool
                {
                    return false;
                }
            };
        }
    })->format(ExportFormat::Excel)->fileName('report');

    $host = new QeHost;
    $host->mountWithTable();

    [, $query, $columns] = $host->buildTableExport(ExportFormat::Csv);

    expect($export->store($query, $columns, 'local'))->toEndWith('.csv');
});
