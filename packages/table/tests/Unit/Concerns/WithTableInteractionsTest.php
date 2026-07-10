<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireCore\Notifications\Contracts\NotificationDriver;
use NyonCode\WireCore\Notifications\Notification;
use NyonCode\WireCore\Notifications\NotificationManager;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\ToggleColumn;
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

class WtiToggleSelectComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtiUser::class)
            ->paginated(false)
            ->columns([
                // Record id 2 is disabled per-record; the rest are editable.
                ToggleColumn::make('active')->disabled(fn (WtiUser $record) => (int) $record->getKey() === 2),
                SelectColumn::make('status')
                    ->options(['open' => 'Open', 'closed' => 'Closed'])
                    ->disabled(fn (WtiUser $record) => (int) $record->getKey() === 2),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtiTsUser extends Model
{
    protected $table = 'wti_ts_users';

    protected $guarded = [];
    // Timestamped: updated_at drives optimistic-lock versioning.
}

class WtiTsComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtiTsUser::class)
            ->paginated(false)
            ->columns([TextInputColumn::make('name')]);
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

class WtiTsNotifyComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtiTsUser::class)
            ->paginated(false)
            ->columns([TextInputColumn::make('name')])
            ->notifyEditConflicts(); // opt in to a toast on conflict
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function wtiTsComponent(): WtiTsComponent
{
    $component = new WtiTsComponent;
    $component->mountWithTable();

    return $component;
}

function wtiTsNotifyComponent(): WtiTsNotifyComponent
{
    $component = new WtiTsNotifyComponent;
    $component->mountWithTable();

    return $component;
}

function wtiToggleSelectComponent(): WtiToggleSelectComponent
{
    $component = new WtiToggleSelectComponent;
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
        $table->boolean('active')->default(false);
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

it('rejects a forged edit to a per-record disabled toggle or select cell', function () {
    $component = wtiToggleSelectComponent();

    // Record id 2 (Alice): active=false, status='closed' to start.
    // Client-side disabled is only cosmetic; the server must reject a forged
    // updateTableCell for a per-record disabled cell and NOT write.
    expect($component->updateTableCell('2', 'active', true)['success'])->toBeFalse()
        ->and($component->updateTableCell('2', 'status', 'open')['success'])->toBeFalse();

    $locked = WtiUser::find(2);
    expect((bool) $locked->active)->toBeFalse()      // unchanged
        ->and($locked->status)->toBe('closed');      // unchanged

    // An enabled record (id 1, Carol: active=false, status='open') still saves.
    expect($component->updateTableCell('1', 'active', true)['success'])->toBeTrue()
        ->and($component->updateTableCell('1', 'status', 'closed')['success'])->toBeTrue();

    $editable = WtiUser::find(1);
    expect((bool) $editable->active)->toBeTrue()
        ->and($editable->status)->toBe('closed');
});

it('rejects a stale edit as an optimistic-lock conflict and returns the current value', function () {
    Schema::create('wti_ts_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    WtiTsUser::create(['name' => 'Amelia']); // updated_at = now

    // A stale, non-zero version ('1') never matches the row's real updated_at →
    // optimistic-lock conflict. The client uses `currentValue`/`currentVersion`
    // to reconcile and surfaces `message` inline on the cell (no NotificationManager
    // required — see toggle/select blades).
    $result = wtiTsComponent()->updateTableCell('1', 'name', 'Renamed', '1');

    expect($result['success'])->toBeFalse()
        ->and($result['conflict'] ?? false)->toBeTrue()
        ->and($result['currentValue'])->toBe('Amelia')
        ->and($result)->toHaveKeys(['message', 'currentVersion']);

    // The edit was rejected — the row is untouched.
    expect(WtiTsUser::find(1)->name)->toBe('Amelia');

    Schema::dropIfExists('wti_ts_users');
});

it('optionally raises a notification on an edit conflict only when opted in', function () {
    Schema::create('wti_ts_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
    WtiTsUser::create(['name' => 'Amelia']);

    $captured = new class implements NotificationDriver
    {
        /** @var array<int, Notification> */
        public array $sent = [];

        public function send(Notification $notification, mixed $livewireComponent = null): void
        {
            $this->sent[] = $notification;
        }
    };
    NotificationManager::setDefaultDriver($captured);

    // Default (opt-out): the conflict is inline-only — no notification.
    wtiTsComponent()->updateTableCell('1', 'name', 'X', '1');
    expect($captured->sent)->toBeEmpty();

    // Opt-in via notifyEditConflicts(): the same conflict also raises a warning.
    wtiTsNotifyComponent()->updateTableCell('1', 'name', 'X', '1');
    expect($captured->sent)->toHaveCount(1)
        ->and($captured->sent[0]->type)->toBe('warning');

    NotificationManager::reset();
    Schema::dropIfExists('wti_ts_users');
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
