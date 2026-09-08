<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Hooks\ImportConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Import\ImportAction;
use NyonCode\WireTable\Import\ImportColumn;
use NyonCode\WireTable\Import\TableImport;
use NyonCode\WireTable\Table;

/*
 * The other half of `export.configuring`.
 *
 * Both are header actions on the same table, declared the same way, so a table
 * whose export could be adjusted and whose import could not was an asymmetry
 * with nothing behind it. Unlike its counterpart this needed no new composition
 * point: `RunImportJob` mounts the host and calls `importTable()`, so the
 * streamed and queued imports were already one path.
 */

class IchRow extends Model
{
    protected $table = 'ich_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class IchComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(IchRow::class)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                ImportAction::makeImport()->importConfig(
                    TableImport::make()
                        ->model(IchRow::class)
                        ->columns([ImportColumn::make('name')->requiredMapping()->rules(['required'])])
                ),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

function ichCsv(string $content): string
{
    $path = (string) tempnam(sys_get_temp_dir(), 'wire-ich-');
    file_put_contents($path, $content);

    return $path;
}

beforeEach(function () {
    Schema::create('ich_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('note')->nullable();
    });
});

afterEach(function () {
    Schema::dropIfExists('ich_rows');

    foreach (glob(sys_get_temp_dir().'/wire-ich-*') ?: [] as $path) {
        @unlink($path);
    }
});

it('lets a hook map a column the table never declared', function () {
    app(PluginManager::class)->hook(
        Hook::ImportConfiguring,
        function (ImportConfiguringPayload $payload): ImportConfiguringPayload {
            $payload->columns = [...$payload->columns, ImportColumn::make('note')];

            return $payload;
        },
    );

    (new IchComponent)->importTable(ichCsv("name,note\nAlice,from-the-hook\n"));

    expect(IchRow::where('name', 'Alice')->value('note'))->toBe('from-the-hook');
});

it('lets a hook reach the import config itself', function () {
    app(PluginManager::class)->hook(
        Hook::ImportConfiguring,
        function (ImportConfiguringPayload $payload): ImportConfiguringPayload {
            $payload->import->updateExisting(['name']);

            return $payload;
        },
    );

    IchRow::create(['name' => 'Alice', 'note' => 'before']);

    $component = new IchComponent;
    $component->importTable(ichCsv("name\nAlice\n"));

    // Updated in place rather than inserted a second time, which is what the
    // hook asked the config for.
    expect(IchRow::where('name', 'Alice')->count())->toBe(1);
});

it('hands the hook the path it is about to read', function () {
    $seen = null;
    $path = ichCsv("name\nAlice\n");

    app(PluginManager::class)->hook(
        Hook::ImportConfiguring,
        function (ImportConfiguringPayload $payload) use (&$seen): ImportConfiguringPayload {
            $seen = $payload->path;

            return $payload;
        },
    );

    (new IchComponent)->importTable($path);

    expect($seen)->toBe($path);
});

it('scopes an import hook by the table model', function () {
    app(PluginManager::class)->hook(
        Hook::ImportConfiguring,
        function (ImportConfiguringPayload $payload): ImportConfiguringPayload {
            $payload->columns = [...$payload->columns, ImportColumn::make('note')];

            return $payload;
        },
        for: IchRow::class,
    );

    (new IchComponent)->importTable(ichCsv("name,note\nAlice,from-the-hook\n"));

    expect(IchRow::where('name', 'Alice')->value('note'))->toBe('from-the-hook');
});

it('leaves a table a scoped import hook does not name alone', function () {
    app(PluginManager::class)->hook(
        Hook::ImportConfiguring,
        function (ImportConfiguringPayload $payload): ImportConfiguringPayload {
            $payload->columns = [...$payload->columns, ImportColumn::make('note')];

            return $payload;
        },
        for: 'some-other-resource',
    );

    (new IchComponent)->importTable(ichCsv("name,note\nAlice,from-the-hook\n"));

    expect(IchRow::where('name', 'Alice')->value('note'))->toBeNull();
});
