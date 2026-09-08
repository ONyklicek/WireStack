<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Drivers\DatabasePreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Drivers\NullPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Models\Preference;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;

/**
 * The per-user store, after it came down from wire-table.
 *
 * It shipped there as `TablePreferenceDriver` + `table_preferences`, because a
 * table's hidden columns were the first thing anyone wanted remembered. A
 * dashboard layout is the same shape — a JSON bag keyed by a surface and a user
 * — and `Widgets/` is in core, which table depends on, so from a widget the
 * table's store could not be reached. Writing a second one would have been a
 * second implementation of one idea; it moved instead.
 *
 * `wire-table`'s own suite still passes unchanged, which is the real proof the
 * move kept the behaviour. These cover what is new: the surface-agnostic
 * vocabulary, the config prefix, and the migration that has to carry an existing
 * installation across.
 */
function preferenceMigration(): object
{
    return require __DIR__.'/../../../../database/migrations/create_wire_preferences_table.php';
}

function preferenceUser(int $id): Authenticatable
{
    $user = new class extends Authenticatable
    {
        protected $guarded = [];
    };

    $user->forceFill(['id' => $id]);

    return $user;
}

beforeEach(function () {
    Schema::dropIfExists('wire_preferences');
    Schema::dropIfExists('table_preferences');
    PreferenceManager::swap(null);
});

afterEach(fn () => PreferenceManager::swap(null));

// ─── The store keeps working, under a name that fits ─────────────────────────

it('stores a bag for any surface, not only a table', function () {
    preferenceMigration()->up();

    $driver = new DatabasePreferenceDriver;
    $user = preferenceUser(1);

    $driver->save('sales-dashboard', $user, ['widgets' => ['order' => ['revenue', 'orders']]]);
    $driver->save('users-table', $user, ['columns' => ['hidden' => ['email']]]);

    expect($driver->load('sales-dashboard', $user))->toBe(['widgets' => ['order' => ['revenue', 'orders']]])
        // Two surfaces, one user, and neither erased the other.
        ->and($driver->load('users-table', $user))->toBe(['columns' => ['hidden' => ['email']]])
        ->and(Preference::count())->toBe(2);
});

it('keeps one user out of another user\'s layout', function () {
    preferenceMigration()->up();

    $driver = new DatabasePreferenceDriver;

    $driver->save('sales', preferenceUser(1), ['mine' => true]);

    expect($driver->load('sales', preferenceUser(2)))->toBe([]);
});

// ─── The migration carries an existing installation ──────────────────────────

it('renames an existing table_preferences rather than leaving it behind', function () {
    // Nobody loses a saved layout over a refactor they did not ask for.
    Schema::create('table_preferences', function (Blueprint $table) {
        $table->id();
        $table->string('user_id')->nullable()->index();
        $table->string('table_key');
        $table->string('view')->default('');
        $table->json('preferences');
        $table->timestamps();
        $table->unique(['user_id', 'table_key', 'view']);
    });

    DB::table('table_preferences')->insert([
        'user_id' => '1',
        'table_key' => 'users-table',
        'view' => '',
        'preferences' => json_encode(['columns' => ['hidden' => ['email']]]),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    preferenceMigration()->up();

    expect(Schema::hasTable('wire_preferences'))->toBeTrue()
        ->and(Schema::hasTable('table_preferences'))->toBeFalse()
        ->and(Schema::hasColumn('wire_preferences', 'surface_key'))->toBeTrue()
        // The row came with it, readable through the new driver.
        ->and((new DatabasePreferenceDriver)->load('users-table', preferenceUser(1)))
        ->toBe(['columns' => ['hidden' => ['email']]]);
});

it('creates the table on a fresh installation', function () {
    preferenceMigration()->up();

    expect(Schema::hasTable('wire_preferences'))->toBeTrue()
        ->and(Schema::hasColumn('wire_preferences', 'surface_key'))->toBeTrue();
});

it('does nothing when it has already run', function () {
    preferenceMigration()->up();
    (new DatabasePreferenceDriver)->save('sales', preferenceUser(1), ['kept' => true]);

    preferenceMigration()->up();

    expect((new DatabasePreferenceDriver)->load('sales', preferenceUser(1)))->toBe(['kept' => true]);
});

// ─── Resolution ──────────────────────────────────────────────────────────────

it('resolves each surface from its own config prefix', function () {
    // A table's layout may be worth a database row while a dashboard's is not,
    // or the other way round — so the prefix is a parameter, not a constant.
    config()->set('a.preferences', [
        'default' => 'database',
        'drivers' => ['database' => DatabasePreferenceDriver::class],
    ]);
    config()->set('b.preferences', [
        'default' => 'session',
        'drivers' => ['session' => SessionPreferenceDriver::class],
    ]);

    expect(PreferenceManager::resolve(configPrefix: 'a.preferences'))->toBeInstanceOf(DatabasePreferenceDriver::class)
        ->and(PreferenceManager::resolve(configPrefix: 'b.preferences'))->toBeInstanceOf(SessionPreferenceDriver::class);
});

it('sends a guest to the guest driver', function () {
    config()->set('a.preferences', [
        'default' => 'database',
        'guest' => 'session',
        'drivers' => [
            'database' => DatabasePreferenceDriver::class,
            'session' => SessionPreferenceDriver::class,
        ],
    ]);

    expect(PreferenceManager::resolve(null, true, 'a.preferences'))->toBeInstanceOf(DatabasePreferenceDriver::class)
        ->and(PreferenceManager::resolve(null, false, 'a.preferences'))->toBeInstanceOf(SessionPreferenceDriver::class);
});

it('remembers nothing rather than failing when nothing is configured', function () {
    expect(PreferenceManager::resolve(configPrefix: 'nothing.here'))->toBeInstanceOf(NullPreferenceDriver::class)
        // An alias pointing at nothing is the same answer.
        ->and(PreferenceManager::resolve(configPrefix: 'also.missing'))->toBeInstanceOf(NullPreferenceDriver::class);
});

it('lets an override and a swap win, in that order', function () {
    $override = new NullPreferenceDriver;
    $swapped = new SessionPreferenceDriver;

    PreferenceManager::swap($swapped);

    expect(PreferenceManager::resolve($override))->toBe($override)
        ->and(PreferenceManager::resolve())->toBe($swapped);
});

it('is one switch for every surface, which is what a test swap has to mean', function () {
    $swapped = new NullPreferenceDriver;

    PreferenceManager::swap($swapped);

    expect(PreferenceManager::resolve(configPrefix: 'a.preferences'))->toBe($swapped)
        ->and(PreferenceManager::resolve(configPrefix: 'b.preferences'))->toBe($swapped);
});

// ─── The contract is the only thing a custom store has to satisfy ────────────

it('accepts a store of somebody else\'s', function () {
    $custom = new class implements PreferenceDriver
    {
        /** @var array<string, array<string, mixed>> */
        public array $bags = [];

        public function load(string $surfaceKey, ?Illuminate\Contracts\Auth\Authenticatable $user, ?string $view = null): array
        {
            return $this->bags[$surfaceKey] ?? [];
        }

        public function save(string $surfaceKey, ?Illuminate\Contracts\Auth\Authenticatable $user, array $preferences, ?string $view = null): void
        {
            $this->bags[$surfaceKey] = $preferences;
        }

        public function forget(string $surfaceKey, ?Illuminate\Contracts\Auth\Authenticatable $user, ?string $view = null): void
        {
            unset($this->bags[$surfaceKey]);
        }

        public function views(string $surfaceKey, ?Illuminate\Contracts\Auth\Authenticatable $user): array
        {
            return [];
        }
    };

    $custom->save('sales', null, ['ok' => true]);

    expect(PreferenceManager::resolve($custom))->toBe($custom)
        ->and($custom->load('sales', null))->toBe(['ok' => true]);
});
