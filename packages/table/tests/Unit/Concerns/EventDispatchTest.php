<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireCore\Core\Events\CellUpdating;
use NyonCode\WireCore\Core\Events\TableFiltered;
use NyonCode\WireCore\Core\Events\TableFiltering;
use NyonCode\WireCore\Core\Events\TableRefreshed;
use NyonCode\WireCore\Core\Events\TableSearched;
use NyonCode\WireCore\Core\Events\TableSearching;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Tests\TestCase;

uses(TestCase::class);

// ─── Test Model ──────────────────────────────────────────────────────────────

class EvtUser extends Model
{
    protected $table = 'evt_users';

    protected $guarded = [];
}

// ─── Test Component (exposes protected methods) ──────────────────────────────

class EventTestComponent
{
    use WithTable;

    public function __construct()
    {
        $this->tableReady = true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->model(EvtUser::class)
            ->columns([
                Column::make('name')->searchable()->editable(),
                Column::make('email')->searchable(),
            ]);
    }

    public function getTable(): Table
    {
        return $this->table(Table::make());
    }

    public function callBuildTableQuery()
    {
        return $this->buildTableQuery();
    }

    public function callInvalidateTable(): void
    {
        $this->invalidateTable();
    }

    public function callUpdateTableCell(mixed $recordKey, string $columnName, mixed $value): array
    {
        return $this->updateTableCell($recordKey, $columnName, $value);
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('evt_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });

    EvtUser::create(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com']);
    EvtUser::create(['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com']);
});

afterEach(function () {
    Schema::dropIfExists('evt_users');
});

// ─── TableRefreshed ──────────────────────────────────────────────────────────

it('dispatches TableRefreshed on invalidateTable', function () {
    Event::fake([TableRefreshed::class]);

    $component = new EventTestComponent;
    $component->callInvalidateTable();

    Event::assertDispatched(TableRefreshed::class, function (TableRefreshed $event) {
        return $event->tableId === EventTestComponent::class;
    });
});

// ─── TableSearching + TableSearched ──────────────────────────────────────────

it('dispatches TableSearching and TableSearched when search is active', function () {
    Event::fake([TableSearching::class, TableSearched::class]);

    $component = new EventTestComponent;
    $component->tableSearch = 'alice';
    $component->callBuildTableQuery();

    Event::assertDispatched(TableSearching::class, function (TableSearching $event) {
        return $event->term === 'alice'
            && $event->tableId === EventTestComponent::class;
    });

    Event::assertDispatched(TableSearched::class, function (TableSearched $event) {
        return $event->term === 'alice'
            && $event->tableId === EventTestComponent::class;
    });
});

it('does not dispatch search events when search is empty', function () {
    Event::fake([TableSearching::class, TableSearched::class]);

    $component = new EventTestComponent;
    $component->tableSearch = '';
    $component->callBuildTableQuery();

    Event::assertNotDispatched(TableSearching::class);
    Event::assertNotDispatched(TableSearched::class);
});

// ─── TableFiltering + TableFiltered ──────────────────────────────────────────

it('dispatches TableFiltering and TableFiltered when filters are active', function () {
    Event::fake([TableFiltering::class, TableFiltered::class]);

    $component = new EventTestComponent;
    $component->tableFilters = ['status' => 'active'];
    $component->callBuildTableQuery();

    Event::assertDispatched(TableFiltering::class, function (TableFiltering $event) {
        return $event->filters === ['status' => 'active']
            && $event->tableId === EventTestComponent::class;
    });

    Event::assertDispatched(TableFiltered::class, function (TableFiltered $event) {
        return $event->filters === ['status' => 'active']
            && $event->tableId === EventTestComponent::class;
    });
});

it('does not dispatch filter events when no filters are active', function () {
    Event::fake([TableFiltering::class, TableFiltered::class]);

    $component = new EventTestComponent;
    $component->tableFilters = [];
    $component->callBuildTableQuery();

    Event::assertNotDispatched(TableFiltering::class);
    Event::assertNotDispatched(TableFiltered::class);
});

// ─── CellUpdating + CellUpdated ──────────────────────────────────────────────

it('dispatches CellUpdating and CellUpdated on successful cell update', function () {
    Event::fake([CellUpdating::class, CellUpdated::class]);

    $component = new EventTestComponent;
    $result = $component->callUpdateTableCell(1, 'name', 'Alice Updated');

    expect($result['success'])->toBeTrue();

    Event::assertDispatched(CellUpdating::class, function (CellUpdating $event) {
        return $event->column === 'name'
            && $event->recordId === 1
            && $event->value === 'Alice Updated';
    });

    Event::assertDispatched(CellUpdated::class, function (CellUpdated $event) {
        return $event->column === 'name'
            && $event->newValue === 'Alice Updated';
    });
});

it('dispatches CellUpdating but not CellUpdated on failed cell update', function () {
    Event::fake([CellUpdating::class, CellUpdated::class]);

    $component = new EventTestComponent;
    $result = $component->callUpdateTableCell(999, 'name', 'Nobody');

    expect($result['success'])->toBeFalse();

    Event::assertDispatched(CellUpdating::class);
    Event::assertNotDispatched(CellUpdated::class);
});
