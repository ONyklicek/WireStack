<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;
use NyonCode\WireTable\Preferences\Drivers\DatabasePreferenceDriver;
use NyonCode\WireTable\Preferences\Drivers\NullPreferenceDriver;
use NyonCode\WireTable\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireTable\Preferences\Models\TablePreference;
use NyonCode\WireTable\Preferences\TablePreferenceManager;
use NyonCode\WireTable\Table;

// ─── Fakes ───────────────────────────────────────────────────────

/** In-memory driver so integration tests don't need a store. */
class ArrayPreferenceDriver implements TablePreferenceDriver
{
    /** @var array<string, array<string, mixed>> */
    public array $store = [];

    public function load(string $tableKey, ?Authenticatable $user): array
    {
        return $this->store[$this->composeKey($tableKey, $user)] ?? [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences): void
    {
        $this->store[$this->composeKey($tableKey, $user)] = $preferences;
    }

    public function forget(string $tableKey, ?Authenticatable $user): void
    {
        unset($this->store[$this->composeKey($tableKey, $user)]);
    }

    private function composeKey(string $tableKey, ?Authenticatable $user): string
    {
        return ($user?->getAuthIdentifier() ?? 'guest').'|'.$tableKey;
    }
}

class PrefUser extends AuthUser
{
    protected $table = 'pref_users';

    protected $guarded = [];

    public $timestamps = false;
}

class PrefTableRow extends Model
{
    protected $table = 'pref_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class RememberingComponent extends Component
{
    public ?string $rememberKey = 'users-index';

    public function table(Table $table): Table
    {
        $table = $table
            ->model(PrefTableRow::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->toggleable(),
                TextColumn::make('email')->toggleable(),
                TextColumn::make('role')->toggleable()->hidden(), // hidden by default
            ]);

        if ($this->rememberKey !== null) {
            $table->rememberColumns($this->rememberKey);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }

    use WithTable;
}

function rememberingComponent(?string $key = 'users-index'): RememberingComponent
{
    $component = new RememberingComponent;
    $component->rememberKey = $key;
    $component->mountWithTable();

    return $component;
}

/** A sub-row table, for the expansion half of the stored view layout. */
class RememberingSubRowComponent extends Component
{
    use WithTable;

    public ?string $rememberKey = 'invoices-index';

    public function table(Table $table): Table
    {
        $table = $table
            ->model(PrefTableRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->subRows('children')
            ->subRowColumns([TextColumn::make('name')]);

        if ($this->rememberKey !== null) {
            $table->rememberColumns($this->rememberKey);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function rememberingSubRowComponent(?string $key = 'invoices-index'): RememberingSubRowComponent
{
    $component = new RememberingSubRowComponent;
    $component->rememberKey = $key;
    $component->mountWithTable();

    return $component;
}

/** Same table but never opts into remembering — for the reset-control render test. */
class PlainColumnsComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(PrefTableRow::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->toggleable(),
                TextColumn::make('email')->toggleable(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('pref_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('role')->nullable();
    });
    Schema::create('pref_users', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
});

afterEach(function () {
    TablePreferenceManager::swap(null);
    Schema::dropIfExists('pref_rows');
    Schema::dropIfExists('pref_users');
    Schema::dropIfExists('table_preferences');
});

// ─── Table fluent API ────────────────────────────────────────────

it('exposes the remember key and preference driver override', function () {
    $driver = new NullPreferenceDriver;
    $table = Table::make()->rememberColumns('orders')->preferenceDriver($driver);

    expect($table->getRememberColumnsKey())->toBe('orders')
        ->and($table->getPreferenceDriver())->toBe($driver);
});

it('has no remember key or driver override by default', function () {
    $table = Table::make();

    expect($table->getRememberColumnsKey())->toBeNull()
        ->and($table->getPreferenceDriver())->toBeNull();
});

// ─── Manager resolution ──────────────────────────────────────────

it('resolves the per-table driver override first', function () {
    $override = new ArrayPreferenceDriver;
    TablePreferenceManager::swap(new NullPreferenceDriver);

    expect(TablePreferenceManager::resolve($override))->toBe($override);
});

it('resolves the swapped driver before config', function () {
    $swapped = new ArrayPreferenceDriver;
    TablePreferenceManager::swap($swapped);

    expect(TablePreferenceManager::resolve())->toBe($swapped);
});

it('resolves the configured default driver for an authenticated user', function () {
    config()->set('wire-table.preferences.default', 'database');
    config()->set('wire-table.preferences.guest', 'session');

    expect(TablePreferenceManager::resolve(null, true))->toBeInstanceOf(DatabasePreferenceDriver::class)
        ->and(TablePreferenceManager::resolve(null, false))->toBeInstanceOf(SessionPreferenceDriver::class);
});

it('falls back to the null driver for an unknown alias', function () {
    config()->set('wire-table.preferences.default', 'does-not-exist');

    expect(TablePreferenceManager::resolve(null, true))->toBeInstanceOf(NullPreferenceDriver::class);
});

// ─── Null driver ─────────────────────────────────────────────────

it('the null driver never remembers anything', function () {
    $driver = new NullPreferenceDriver;
    $driver->save('k', null, ['columns' => ['hidden' => ['a']]]);
    $driver->forget('k', null);

    expect($driver->load('k', null))->toBe([]);
});

// ─── Session driver ──────────────────────────────────────────────

it('the session driver stores, loads and forgets per user', function () {
    $driver = new SessionPreferenceDriver;
    $user = tap(new PrefUser)->forceFill(['id' => 7]);

    $driver->save('orders', $user, ['columns' => ['hidden' => ['total']]]);

    expect($driver->load('orders', $user))->toBe(['columns' => ['hidden' => ['total']]])
        // A different user does not see it.
        ->and($driver->load('orders', tap(new PrefUser)->forceFill(['id' => 8])))->toBe([])
        // A guest does not see it.
        ->and($driver->load('orders', null))->toBe([]);

    $driver->forget('orders', $user);
    expect($driver->load('orders', $user))->toBe([]);
});

it('the session driver ignores a non-array stored value', function () {
    Session::put('wire-table.preferences.guest.orders', 'corrupt');

    expect((new SessionPreferenceDriver)->load('orders', null))->toBe([]);
});

// ─── Database driver ─────────────────────────────────────────────

it('the database driver persists per (user, table) and is scoped', function () {
    Schema::create('table_preferences', function (Blueprint $table) {
        $table->id();
        $table->string('user_id')->nullable()->index();
        $table->string('table_key');
        $table->json('preferences');
        $table->timestamps();
        $table->unique(['user_id', 'table_key']);
    });

    $driver = new DatabasePreferenceDriver;
    $alice = tap(new PrefUser)->forceFill(['id' => 1]);
    $bob = tap(new PrefUser)->forceFill(['id' => 2]);

    $driver->save('orders', $alice, ['columns' => ['hidden' => ['email']]]);
    $driver->save('orders', $bob, ['columns' => ['hidden' => ['role']]]);
    // updateOrCreate: saving again replaces, does not duplicate.
    $driver->save('orders', $alice, ['columns' => ['hidden' => ['email', 'role']]]);

    expect($driver->load('orders', $alice))->toBe(['columns' => ['hidden' => ['email', 'role']]])
        ->and($driver->load('orders', $bob))->toBe(['columns' => ['hidden' => ['role']]])
        ->and(TablePreference::count())->toBe(2);

    $driver->forget('orders', $alice);
    expect($driver->load('orders', $alice))->toBe([])
        ->and($driver->load('orders', $bob))->toBe(['columns' => ['hidden' => ['role']]]);
});

// ─── WithTable integration ───────────────────────────────────────

it('keeps configured defaults when nothing is stored', function () {
    TablePreferenceManager::swap(new ArrayPreferenceDriver);

    $component = rememberingComponent();

    // role starts hidden by default; nothing else.
    expect($component->tableState->get('columns.hidden'))->toBe(['role']);
});

it('loads a stored hidden-column set over the defaults', function () {
    $driver = new ArrayPreferenceDriver;
    $driver->store['guest|users-index'] = ['columns' => ['hidden' => ['email']]];
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();

    // Stored set wins: email hidden, role now shown.
    expect($component->tableState->get('columns.hidden'))->toBe(['email']);
});

it('drops stale column names from a stored set', function () {
    $driver = new ArrayPreferenceDriver;
    $driver->store['guest|users-index'] = ['columns' => ['hidden' => ['email', 'ghost-column']]];
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();

    expect($component->tableState->get('columns.hidden'))->toBe(['email']);
});

it('persists the hidden set when a column is toggled', function () {
    $driver = new ArrayPreferenceDriver;
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();
    $component->toggleColumn('email'); // hide email (role already hidden)

    expect($driver->store['guest|users-index']['columns']['hidden'])
        ->toContain('email')
        ->toContain('role');
});

it('forgets the stored set when columns are reset', function () {
    $driver = new ArrayPreferenceDriver;
    $driver->store['guest|users-index'] = ['columns' => ['hidden' => ['email']]];
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();
    $component->resetColumns();

    expect($driver->store)->not->toHaveKey('guest|users-index')
        // reset restores the configured default (role hidden).
        ->and($component->tableState->get('columns.hidden'))->toBe(['role']);
});

it('remembers a toggle across a fresh mount (round-trip)', function () {
    TablePreferenceManager::swap(new ArrayPreferenceDriver);

    // First "page load": hide email, then discard the component.
    $first = rememberingComponent();
    $first->toggleColumn('email');

    // Second "page load": a brand-new component reads the persisted layout.
    $second = rememberingComponent();

    expect($second->tableState->get('columns.hidden'))
        ->toContain('email')
        ->toContain('role')
        ->and($second->isColumnVisible('email'))->toBeFalse();
});

it('does not persist when the table has not opted in', function () {
    $driver = new ArrayPreferenceDriver;
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent(key: null); // no rememberColumns()
    $component->toggleColumn('email');

    expect($driver->store)->toBe([]);
});

// ─── Sub-row expansion rides along with the column layout ────────

it('persists the sub-row expansion baseline for the user', function () {
    $driver = new ArrayPreferenceDriver;
    TablePreferenceManager::swap($driver);

    $component = rememberingSubRowComponent();
    $component->toggleAllRowExpansion();

    expect($driver->store['guest|invoices-index']['rows']['expandAll'])->toBeTrue();
});

it('restores the expansion baseline on a fresh mount', function () {
    TablePreferenceManager::swap(new ArrayPreferenceDriver);

    $first = rememberingSubRowComponent();
    $first->toggleAllRowExpansion();

    $second = rememberingSubRowComponent();

    expect($second->expandsSubRowsByDefault())->toBeTrue()
        ->and($second->isRowExpanded(1))->toBeTrue();
});

it('ignores a stored baseline for a table without sub-rows', function () {
    $driver = new ArrayPreferenceDriver;
    $driver->store['guest|users-index'] = ['rows' => ['expandAll' => true]];
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();

    expect($component->tableState->get('rows.expandAll'))->toBeNull();
});

it('does not persist the baseline when the table has not opted in', function () {
    $driver = new ArrayPreferenceDriver;
    TablePreferenceManager::swap($driver);

    $component = rememberingSubRowComponent(key: null);
    $component->toggleAllRowExpansion();

    expect($driver->store)->toBe([])
        // The choice still applies for this component's lifetime.
        ->and($component->expandsSubRowsByDefault())->toBeTrue();
});

it('renders a reset-columns control only when remembering is enabled', function () {
    TablePreferenceManager::swap(new ArrayPreferenceDriver);

    // RememberingComponent defaults to rememberColumns('users-index').
    Livewire::test(RememberingComponent::class)->assertSee('Reset columns');

    Livewire::test(PlainColumnsComponent::class)->assertDontSee('Reset columns');
});
