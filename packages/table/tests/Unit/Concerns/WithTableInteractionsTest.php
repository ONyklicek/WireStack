<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

// ─── Test model & component ──────────────────────────────────────

class WtiUser extends Model
{
    protected $table = 'wti_users';

    protected $guarded = [];

    public $timestamps = false;
}

class WtiComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtiUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->toggleable(),
                TextColumn::make('status')->toggleable()->filterAsSelect(['open' => 'Open', 'closed' => 'Closed']),
                TextColumn::make('priority')->sortable()->toggleable(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtiEditableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtiUser::class)
            ->paginated(false)
            ->columns([
                TextInputColumn::make('name')->required(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function wtiComponent(): WtiComponent
{
    $component = new WtiComponent;
    $component->mountWithTable();

    return $component;
}

function wtiEditableComponent(): WtiEditableComponent
{
    $component = new WtiEditableComponent;
    $component->mountWithTable();

    return $component;
}

beforeEach(function () {
    Schema::create('wti_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('open');
        $table->integer('priority')->default(1);
    });

    WtiUser::insert([
        ['name' => 'Carol', 'status' => 'open', 'priority' => 3],
        ['name' => 'Alice', 'status' => 'closed', 'priority' => 1],
        ['name' => 'Bob', 'status' => 'open', 'priority' => 2],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('wti_users');
});

// ─── Records ─────────────────────────────────────────────────────

it('loads table records', function () {
    $records = wtiComponent()->getTableRecords();

    expect($records)->toBeInstanceOf(Collection::class)
        ->and($records)->toHaveCount(3);
});

// ─── Column visibility ───────────────────────────────────────────

it('toggles column visibility and protects the last visible column', function () {
    $c = wtiComponent();

    expect($c->isColumnVisible('priority'))->toBeTrue();

    $c->toggleColumn('priority');
    expect($c->isColumnVisible('priority'))->toBeFalse();

    $c->toggleColumn('priority');
    expect($c->isColumnVisible('priority'))->toBeTrue();

    // Hiding down to a single visible column is blocked.
    $c->toggleColumn('status');
    $c->toggleColumn('priority');
    $c->toggleColumn('name'); // would hide the last → ignored

    expect($c->isColumnVisible('name'))->toBeTrue();
});

// ─── Sorting ─────────────────────────────────────────────────────

it('sorts ascending then toggles to descending', function () {
    $c = wtiComponent();

    $c->sortTable('name');
    $c->invalidateTable();
    expect($c->getTableRecords()->first()->name)->toBe('Alice');

    $c->sortTable('name');
    $c->invalidateTable();
    expect($c->getTableRecords()->first()->name)->toBe('Carol');

    // Switching to a different column resets to ascending.
    $c->sortTable('priority');
    $c->invalidateTable();
    expect($c->getTableRecords()->first()->priority)->toBe(1);
});

// ─── Selection ───────────────────────────────────────────────────

it('manages record selection state', function () {
    $c = wtiComponent();

    expect($c->getSelectedRecordsCount())->toBe(0)
        ->and($c->areSomeVisibleSelected())->toBeFalse()
        ->and($c->areAllVisibleSelected())->toBeFalse();

    $c->toggleRecordSelection('1');
    expect($c->isRecordSelected('1'))->toBeTrue()
        ->and($c->getSelectedRecordsCount())->toBe(1)
        ->and($c->areSomeVisibleSelected())->toBeTrue()
        ->and($c->areAllVisibleSelected())->toBeFalse()
        ->and($c->getSelectedRecords())->toHaveCount(1);

    // Toggling again removes it.
    $c->toggleRecordSelection('1');
    expect($c->isRecordSelected('1'))->toBeFalse();

    $c->selectAllRecords();
    expect($c->areAllVisibleSelected())->toBeTrue()
        ->and($c->getSelectedRecordsCount())->toBe(3)
        ->and($c->getSelectedRecordKeys())->toHaveCount(3)
        ->and($c->getSelectedRecords())->toHaveCount(3);

    $c->deselectAllRecords();
    expect($c->getSelectedRecordsCount())->toBe(0)
        ->and($c->getSelectedRecords())->toBeEmpty();
});

// ─── Polling ─────────────────────────────────────────────────────

it('exposes polling controls with sane defaults', function () {
    $c = wtiComponent();

    expect($c->shouldPoll())->toBeFalse()
        ->and($c->getTablePollingConfig())->toBe(['enabled' => false])
        ->and($c->getTablePollingAttribute())->toBeNull();

    $c->resumeTablePolling();
    $c->pauseTablePolling();
    $c->toggleTablePolling();

    // Not configured to poll, so still resolves to false.
    expect($c->shouldPoll())->toBeFalse();
});

// ─── Filter resets ───────────────────────────────────────────────

it('resets filters without breaking the query', function () {
    $c = wtiComponent();

    $c->resetColumnFilters();
    $c->removeTableFilter('status');
    $c->removeTableFilter('relation.field');
    $c->resetTableFilters();

    expect($c->getTableRecords())->toHaveCount(3);
});

// ─── SQL & column debug helpers ──────────────────────────────────

it('exposes SQL and column debug helpers', function () {
    $c = wtiComponent();

    expect(strtolower($c->getTableSql()))->toContain('select')
        ->and($c->getTableRawSql())->toBeArray()
        ->and($c->getTableColumnNames())->toContain('name', 'status', 'priority')
        ->and($c->getTableColumnsInfo())->toBeArray()
        ->and($c->getTableDatabaseColumns())->toBeArray()
        ->and($c->getTableDatabaseColumnsInfo())->toBeArray();
});

// ─── Row expansion ───────────────────────────────────────────────

it('toggles row expansion state', function () {
    $c = wtiComponent();

    expect($c->isRowExpanded('1'))->toBeFalse();

    $c->toggleRowExpansion('1');
    expect($c->isRowExpanded('1'))->toBeTrue();

    $c->toggleRowExpansion('1');
    expect($c->isRowExpanded('1'))->toBeFalse();

    // No sub-rows configured: expand/collapse-all are safe no-ops.
    $c->expandAllRows();
    $c->collapseAllRows();
    $c->toggleFlattenMode();

    expect($c->isRowExpanded('1'))->toBeFalse();
});

// ─── Inline cell editing ─────────────────────────────────────────

it('rejects updates to unknown or non-editable columns', function () {
    expect(wtiEditableComponent()->updateTableCell('1', 'nope', 'x')['success'])->toBeFalse()
        ->and(wtiComponent()->updateTableCell('1', 'name', 'x')['success'])->toBeFalse();
});

it('updates an editable cell and persists the value', function () {
    $result = wtiEditableComponent()->updateTableCell('1', 'name', 'Renamed');

    expect($result['success'])->toBeTrue()
        ->and(WtiUser::find(1)->name)->toBe('Renamed');
});

it('fails to update when the value is invalid', function () {
    $result = wtiEditableComponent()->updateTableCell('1', 'name', '');

    expect($result['success'])->toBeFalse()
        ->and($result['errors'] ?? [])->not->toBeEmpty()
        ->and(WtiUser::find(1)->name)->toBe('Carol');
});

it('fails to update a missing record', function () {
    expect(wtiEditableComponent()->updateTableCell('999', 'name', 'X')['success'])->toBeFalse();
});

it('validates a cell value without saving', function () {
    $c = wtiEditableComponent();

    expect($c->validateTableCell('1', 'name', 'Valid')['valid'])->toBeTrue()
        ->and($c->validateTableCell('1', 'name', '')['valid'])->toBeFalse()
        ->and($c->validateTableCell('1', 'nope', 'x')['valid'])->toBeFalse()
        ->and($c->validateTableCell('999', 'name', 'x')['valid'])->toBeFalse();
});
