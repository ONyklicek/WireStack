<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireCore\Core\Plugin\Hooks\ExportConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Export\ExportFormat;
use NyonCode\WireTable\Table;

/*
 * What a table would export, adjustable by something that did not build it.
 *
 * The dispatch site is `buildTableExport()` because that is where the two
 * deliveries meet: a streamed download and a queued file are the same call, so an
 * export that changed depending on how it was delivered is not expressible. A
 * hook on `exportTable()` would have left the queued copy uncovered, and nobody
 * would have seen it until they compared two files.
 */

class EchRow extends Model
{
    protected $table = 'ech_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class EchComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(EchRow::class)
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('cost'),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    Schema::create('ech_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('cost');
        $table->boolean('approved')->default(true);
    });

    EchRow::create(['name' => 'Alice', 'cost' => '10', 'approved' => true]);
    EchRow::create(['name' => 'Bob', 'cost' => '20', 'approved' => false]);
});

afterEach(function () {
    Schema::dropIfExists('ech_rows');
});

function echComponent(): EchComponent
{
    $component = new EchComponent;
    $component->mountWithTable();

    return $component;
}

function echRows(EchComponent $component): array
{
    [, $query, $columns] = $component->buildTableExport(ExportFormat::Csv);

    return [
        $query->pluck('name')->all(),
        array_map(fn (TextColumn $column): string => $column->getName(), $columns),
    ];
}

it('lets a hook constrain the query the export would run', function () {
    app(PluginManager::class)->hook(
        Hook::ExportConfiguring,
        function (ExportConfiguringPayload $payload): ExportConfiguringPayload {
            $payload->query->where('approved', true);

            return $payload;
        },
    );

    [$names] = echRows(echComponent());

    expect($names)->toBe(['Alice']);
});

it('lets a hook drop a column from the file', function () {
    app(PluginManager::class)->hook(
        Hook::ExportConfiguring,
        function (ExportConfiguringPayload $payload): ExportConfiguringPayload {
            $payload->columns = array_values(array_filter(
                $payload->columns,
                fn (TextColumn $column): bool => $column->getName() !== 'cost',
            ));

            return $payload;
        },
    );

    [, $columns] = echRows(echComponent());

    expect($columns)->toBe(['name']);
});

it('hands the hook what the file would contain, not everything declared', function () {
    // After the visibility filter, deliberately: a callback that had to
    // re-derive which columns are hidden would be holding a second copy of the
    // rule, and the copy nobody renders is the one that drifts.
    $seen = null;

    app(PluginManager::class)->hook(
        Hook::ExportConfiguring,
        function (ExportConfiguringPayload $payload) use (&$seen): ExportConfiguringPayload {
            $seen = array_map(fn (TextColumn $column): string => $column->getName(), $payload->columns);

            return $payload;
        },
    );

    $component = echComponent();
    $component->tableState->set('columns.hidden', ['cost']);

    echRows($component);

    expect($seen)->toBe(['name']);
});

it('scopes an export hook by the table model', function () {
    app(PluginManager::class)->hook(
        Hook::ExportConfiguring,
        function (ExportConfiguringPayload $payload): ExportConfiguringPayload {
            $payload->query->where('approved', true);

            return $payload;
        },
        for: EchRow::class,
    );

    [$names] = echRows(echComponent());

    expect($names)->toBe(['Alice']);
});

it('leaves a table a scoped hook does not name alone', function () {
    app(PluginManager::class)->hook(
        Hook::ExportConfiguring,
        function (ExportConfiguringPayload $payload): ExportConfiguringPayload {
            $payload->query->where('approved', true);

            return $payload;
        },
        for: 'some-other-resource',
    );

    [$names] = echRows(echComponent());

    expect($names)->toBe(['Alice', 'Bob']);
});
