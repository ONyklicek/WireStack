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
use NyonCode\WireTable\Exceptions\ImportException;
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportColumn;
use NyonCode\WireTable\Import\Jobs\RunImportJob;
use NyonCode\WireTable\Import\TableImport;
use NyonCode\WireTable\Table;

/*
 * An import that runs after the request that uploaded the file.
 *
 * The opposite shape to the queued export: an import was already path-in,
 * result-out, so none of the import pipeline changed. What the job adds is the
 * three things it cannot borrow from a request — a file that outlives it, a
 * result with somewhere to go, and a failure that is visible.
 */
class QiRow extends Model
{
    protected $table = 'qi_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class QiHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(QiRow::class)
            ->headerActions([
                ImportAction::makeImport()->importConfig(
                    TableImport::make()
                        ->model(QiRow::class)
                        ->columns([
                            ImportColumn::make('name')->rules(['required']),
                        ])
                ),
            ])
            ->columns([TextColumn::make('name')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class QiStatefulHost extends QiHost
{
    public function table(Table $table): Table
    {
        // Reads the state bag while building — a table with a filter default or
        // a state-derived sort does this. $tableState is a typed property that
        // mountWithTable() initialises; unmounted, touching it is a fatal.
        $table = parent::table($table);

        return $table->searchable($this->tableState->get('search', '') !== '');
    }
}

beforeEach(function () {
    Schema::create('qi_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });

    Storage::fake('local');
    Storage::disk('local')->put('imports/people.csv', "name\nAda\nGrace\n");
});

afterEach(function () {
    Schema::dropIfExists('qi_rows');
});

it('dispatches instead of importing, and says it is running', function () {
    Queue::fake();

    Livewire::test(QiHost::class)
        ->call('queueTableImport', 'imports/people.csv', 'local')
        ->assertDispatched('table-notification');

    Queue::assertPushed(RunImportJob::class, function (RunImportJob $job): bool {
        return $job->host === QiHost::class
            && $job->path === 'imports/people.csv'
            && $job->disk === 'local';
    });
});

it('imports the rows the file holds', function () {
    (new RunImportJob(QiHost::class, 'imports/people.csv', 'local'))->handle();

    expect(QiRow::pluck('name')->all())->toBe(['Ada', 'Grace']);
});

it('reads the upload off the disk, not off the local filesystem', function () {
    // The worker is entitled to be another machine, and CsvImporter opens with
    // fopen(), which does not speak S3. The disk path is not a real path: if the
    // job handed it straight to fopen() nothing would be imported.
    $job = new RunImportJob(QiHost::class, 'imports/people.csv', 'local');

    expect(file_exists('imports/people.csv'))->toBeFalse();

    $job->handle();

    expect(QiRow::count())->toBe(2);
});

it('leaves no temp file behind', function () {
    $before = glob(sys_get_temp_dir().'/wire-import-*') ?: [];

    (new RunImportJob(QiHost::class, 'imports/people.csv', 'local'))->handle();

    expect(glob(sys_get_temp_dir().'/wire-import-*') ?: [])->toBe($before);
});

it('fails loudly when the file is gone', function () {
    // The one that matters. CsvImporter treats an unreadable path as "no rows",
    // which is right when the user is watching and a lie on a queue: "imported 0
    // row(s), 0 failed" is indistinguishable from an empty file. A worker that
    // cannot find the upload must fail and retry, not report a clean no-op.
    (new RunImportJob(QiHost::class, 'imports/vanished.csv', 'local'))->handle();
})->throws(ImportException::class, 'does not exist on disk');

it('reports what it imported', function () {
    $sent = [];

    NotificationManager::setDefaultDriver(new class($sent) implements NotificationDriver
    {
        public function __construct(public array &$sent) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification->message;
        }
    });

    (new RunImportJob(QiHost::class, 'imports/people.csv', 'local'))->handle();

    expect($sent)->toHaveCount(1)
        ->and($sent[0])->toContain('2');

    NotificationManager::reset();
});

it('reports rejected rows rather than swallowing them', function () {
    // A queued import has no return value, so a row the validator refused is
    // only ever seen in the notification.
    Storage::disk('local')->put('imports/partial.csv', "name\nAda\n\"\"\n");

    $sent = [];

    NotificationManager::setDefaultDriver(new class($sent) implements NotificationDriver
    {
        public function __construct(public array &$sent) {}

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification->type.':'.$notification->message;
        }
    });

    (new RunImportJob(QiHost::class, 'imports/partial.csv', 'local'))->handle();

    // Warning, not success: an import that dropped a row did not simply succeed.
    expect($sent[0])->toBe('warning:Imported 1 row(s), 1 failed.');

    NotificationManager::reset();
});

it('mounts the host before asking it for its table', function () {
    // Not decoration: a table that reads the state bag while building is the
    // common shape, and an unmounted host fatals on the typed property rather
    // than importing.
    (new RunImportJob(QiStatefulHost::class, 'imports/people.csv', 'local'))->handle();

    expect(QiRow::count())->toBe(2);
});

it('does nothing for a host that cannot import', function () {
    (new RunImportJob(QiRow::class, 'imports/people.csv', 'local'))->handle();

    expect(QiRow::count())->toBe(0);
});

it('carries a class and a path, not a file', function () {
    $job = new RunImportJob(QiHost::class, 'imports/people.csv', 'local');

    expect(unserialize(serialize($job)))->toBeInstanceOf(RunImportJob::class);
});
