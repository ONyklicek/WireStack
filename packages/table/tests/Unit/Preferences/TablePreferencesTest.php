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

    public function load(string $tableKey, ?Authenticatable $user, ?string $view = null): array
    {
        return $this->store[$this->composeKey($tableKey, $user, $view)] ?? [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
    {
        $this->store[$this->composeKey($tableKey, $user, $view)] = $preferences;
    }

    public function forget(string $tableKey, ?Authenticatable $user, ?string $view = null): void
    {
        unset($this->store[$this->composeKey($tableKey, $user, $view)]);
    }

    public function views(string $tableKey, ?Authenticatable $user): array
    {
        $prefix = $this->composeKey($tableKey, $user, '');

        $names = [];
        foreach (array_keys($this->store) as $key) {
            if ($key !== $prefix && str_starts_with($key, $prefix)) {
                $names[] = substr($key, strlen($prefix));
            }
        }

        return $names;
    }

    private function composeKey(string $tableKey, ?Authenticatable $user, ?string $view = null): string
    {
        return ($user?->getAuthIdentifier() ?? 'guest').'|'.$tableKey.'|'.($view ?? '');
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

/**
 * The real migration, not a copy of it.
 *
 * This test used to declare the schema itself, so the day the migration grew a
 * `view` column the copy stayed behind and every database-driver test failed on
 * a column that was right there in the package. Loading the migration keeps one
 * owner for the shape.
 */
function createTablePreferencesSchema(): void
{
    (require __DIR__.'/../../../database/migrations/create_table_preferences_table.php')->up();
}

// ─── Database driver ─────────────────────────────────────────────

it('the database driver persists per (user, table) and is scoped', function () {
    createTablePreferencesSchema();

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
    $driver->store['guest|users-index|'] = ['columns' => ['hidden' => ['email']]];
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();

    // Stored set wins: email hidden, role now shown.
    expect($component->tableState->get('columns.hidden'))->toBe(['email']);
});

it('drops stale column names from a stored set', function () {
    $driver = new ArrayPreferenceDriver;
    $driver->store['guest|users-index|'] = ['columns' => ['hidden' => ['email', 'ghost-column']]];
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();

    expect($component->tableState->get('columns.hidden'))->toBe(['email']);
});

it('persists the hidden set when a column is toggled', function () {
    $driver = new ArrayPreferenceDriver;
    TablePreferenceManager::swap($driver);

    $component = rememberingComponent();
    $component->toggleColumn('email'); // hide email (role already hidden)

    expect($driver->store['guest|users-index|']['columns']['hidden'])
        ->toContain('email')
        ->toContain('role');
});

it('forgets the stored set when columns are reset', function () {
    $driver = new ArrayPreferenceDriver;
    $driver->store['guest|users-index|'] = ['columns' => ['hidden' => ['email']]];
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

    expect($driver->store['guest|invoices-index|']['rows']['expandAll'])->toBeTrue();
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
    $driver->store['guest|users-index|'] = ['rows' => ['expandAll' => true]];
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

// ─── Named views ─────────────────────────────────────────────────

it('keeps a named view apart from the current layout', function () {
    // The point of growing a dimension instead of a second store: the layout a
    // user is looking at and the one they saved are the same shape under
    // different names, and neither may overwrite the other.
    createTablePreferencesSchema();

    $driver = new DatabasePreferenceDriver;
    $alice = tap(new PrefUser)->forceFill(['id' => 1]);

    $driver->save('orders', $alice, ['columns' => ['hidden' => ['email']]]);
    $driver->save('orders', $alice, ['columns' => ['hidden' => ['role']]], 'Unpaid');

    expect($driver->load('orders', $alice))->toBe(['columns' => ['hidden' => ['email']]])
        ->and($driver->load('orders', $alice, 'Unpaid'))->toBe(['columns' => ['hidden' => ['role']]]);
});

it('lists the saved names without offering the current layout as one', function () {
    createTablePreferencesSchema();

    $driver = new DatabasePreferenceDriver;
    $alice = tap(new PrefUser)->forceFill(['id' => 1]);
    $bob = tap(new PrefUser)->forceFill(['id' => 2]);

    $driver->save('orders', $alice, ['columns' => ['hidden' => []]]);
    $driver->save('orders', $alice, ['columns' => ['hidden' => []]], 'Unpaid');
    $driver->save('orders', $alice, ['columns' => ['hidden' => []]], 'Overdue');
    $driver->save('orders', $bob, ['columns' => ['hidden' => []]], 'Bob only');

    // The unnamed layout has no name to show in a switcher, and offering it
    // would let a user "restore" the state they are already in.
    expect($driver->views('orders', $alice))->toEqualCanonicalizing(['Unpaid', 'Overdue'])
        ->and($driver->views('orders', $bob))->toBe(['Bob only'])
        ->and($driver->views('other-table', $alice))->toBe([]);
});

it('forgets one named view and leaves the rest standing', function () {
    createTablePreferencesSchema();

    $driver = new DatabasePreferenceDriver;
    $alice = tap(new PrefUser)->forceFill(['id' => 1]);

    $driver->save('orders', $alice, ['columns' => ['hidden' => ['a']]]);
    $driver->save('orders', $alice, ['columns' => ['hidden' => ['b']]], 'Unpaid');

    $driver->forget('orders', $alice, 'Unpaid');

    expect($driver->views('orders', $alice))->toBe([])
        ->and($driver->load('orders', $alice, 'Unpaid'))->toBe([])
        // Resetting a saved view is not resetting the table.
        ->and($driver->load('orders', $alice))->toBe(['columns' => ['hidden' => ['a']]]);
});

it('replaces a named view rather than duplicating it', function () {
    createTablePreferencesSchema();

    $driver = new DatabasePreferenceDriver;
    $alice = tap(new PrefUser)->forceFill(['id' => 1]);

    $driver->save('orders', $alice, ['columns' => ['hidden' => ['a']]], 'Unpaid');
    $driver->save('orders', $alice, ['columns' => ['hidden' => ['b']]], 'Unpaid');

    expect($driver->views('orders', $alice))->toBe(['Unpaid'])
        ->and($driver->load('orders', $alice, 'Unpaid'))->toBe(['columns' => ['hidden' => ['b']]]);
});

it('gives a shared view no user, so sharing needs no second mechanism', function () {
    createTablePreferencesSchema();

    $driver = new DatabasePreferenceDriver;
    $alice = tap(new PrefUser)->forceFill(['id' => 1]);

    $driver->save('orders', null, ['columns' => ['hidden' => ['secret']]], 'Team view');

    expect($driver->load('orders', null, 'Team view'))->toBe(['columns' => ['hidden' => ['secret']]])
        // It is not Alice's, so it is not in her list — whoever shows a shared
        // view asks for it, rather than finding it mixed into a personal one.
        ->and($driver->views('orders', $alice))->toBe([]);
});

it('the session driver keeps named views apart and indexes their names', function () {
    $driver = new SessionPreferenceDriver;

    $driver->save('orders', null, ['columns' => ['hidden' => ['a']]]);
    $driver->save('orders', null, ['columns' => ['hidden' => ['b']]], 'Unpaid');

    expect($driver->load('orders', null))->toBe(['columns' => ['hidden' => ['a']]])
        ->and($driver->load('orders', null, 'Unpaid'))->toBe(['columns' => ['hidden' => ['b']]])
        ->and($driver->views('orders', null))->toBe(['Unpaid']);

    $driver->forget('orders', null, 'Unpaid');

    // The name goes with the bag: a name left in the index would offer a
    // switcher entry that restores nothing.
    expect($driver->views('orders', null))->toBe([])
        ->and($driver->load('orders', null, 'Unpaid'))->toBe([]);
});

it('the null driver has no views to list', function () {
    expect((new NullPreferenceDriver)->views('orders', null))->toBe([]);
});

it('the session driver survives a dot in a view name', function () {
    // Session::put() reads dots as nesting. Hanging a named view off the current
    // layout's key wrote it INSIDE that layout's bag — the current layout came
    // back with a stray 'view' entry in it — and a name like this would have done
    // the same thing one level deeper. Names are array keys now, not key
    // fragments.
    $driver = new SessionPreferenceDriver;

    $driver->save('orders', null, ['columns' => ['hidden' => ['a']]]);
    $driver->save('orders', null, ['columns' => ['hidden' => ['b']]], 'Q1.2026');

    expect($driver->load('orders', null))->toBe(['columns' => ['hidden' => ['a']]])
        ->and($driver->load('orders', null, 'Q1.2026'))->toBe(['columns' => ['hidden' => ['b']]])
        ->and($driver->views('orders', null))->toBe(['Q1.2026']);
});
