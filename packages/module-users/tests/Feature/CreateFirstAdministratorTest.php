<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Install\CreateFirstAdministrator;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use Spatie\Permission\Models\Role;

/*
 * Somebody to sign in as — the gap nothing in this framework covered.
 *
 * Every package installed, every migration run, the shell scaffolded, the routes
 * registered, and then a login screen with no account behind it. The documented
 * answer was `php artisan tinker`.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers  In the order the step asks for them.
 * @param  array<int, string>  $said  Filled with every note and warning.
 */
function cfaConsole(array $answers, array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($answers, $said, $interactive) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(
            private array $answers,
            private array &$said,
            private bool $interactive,
        ) {}

        public function ask(string $question, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function secret(string $question): string
        {
            return array_shift($this->answers) ?? '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return (string) $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return $this->interactive;
        }
    };
}

function cfaStep(): CreateFirstAdministrator
{
    return new CreateFirstAdministrator;
}

function cfaUsersTable(): void
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
}

function cfaRoleTables(): void
{
    Schema::create('roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('guard_name');
        $table->timestamps();
        $table->unique(['name', 'guard_name']);
    });

    Schema::create('model_has_roles', function (Blueprint $table) {
        $table->unsignedBigInteger('role_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['role_id', 'model_id', 'model_type']);
    });

    Schema::create('model_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->string('model_type');
        $table->unsignedBigInteger('model_id');
        $table->primary(['permission_id', 'model_id', 'model_type']);
    });

    Schema::create('role_has_permissions', function (Blueprint $table) {
        $table->unsignedBigInteger('permission_id');
        $table->unsignedBigInteger('role_id');
        $table->primary(['permission_id', 'role_id']);
    });
}

it('is contributed by this module, so the installer never learns what a user is', function () {
    expect(SetupRegistry::instance()->all())->toContain(CreateFirstAdministrator::class);
});

it('is pending while nobody can sign in', function () {
    cfaUsersTable();

    expect(cfaStep()->state())->toBe(SetupState::Pending)
        ->and(cfaStep()->summary())->toContain('create an account')
        ->and(cfaStep()->label())->toBe('First administrator')
        ->and(cfaStep()->sort())->toBe(400);
});

it('is done the moment there is an account, and never offers a second', function () {
    // Not a user-management command: an installer that offered to add another
    // administrator on every run is one nobody could run twice safely.
    cfaUsersTable();
    User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => Hash::make('x')]);

    expect(cfaStep()->state())->toBe(SetupState::Done)
        ->and(cfaStep()->summary())->toBe('an account already exists, so you can sign in');
});

it('is blocked, not offered, before the users table exists', function () {
    expect(cfaStep()->state())->toBe(SetupState::Blocked)
        ->and(cfaStep()->summary())->toContain('not there yet');
});

it('is blocked when the application has no user model to point at', function () {
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    expect(cfaStep()->state())->toBe(SetupState::Blocked)
        ->and(cfaStep()->summary())->toContain('no user model');

    config()->set('wire-module-users.model', '');

    expect(cfaStep()->state())->toBe(SetupState::Blocked);
});

it('creates the account, with the password hashed', function () {
    cfaUsersTable();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Ondřej', 'o@example.com', 'hunter2'], $said)))
        ->toBe(SetupOutcome::Applied);

    $user = User::first();

    expect($user->name)->toBe('Ondřej')
        ->and($user->email)->toBe('o@example.com')
        ->and($user->password)->not->toBe('hunter2')
        ->and(Hash::check('hunter2', $user->password))->toBeTrue()
        ->and($said)->toContain('Created o@example.com.');
});

it('writes to the columns this application calls its own', function () {
    // `wire-module-users.fields` exists because a `users` table is the one table
    // every application has changed.
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('full_name');
        $table->string('login')->unique();
        $table->string('secret');
        $table->timestamps();
    });

    config()->set('wire-module-users.fields', [
        'name' => 'full_name',
        'email' => 'login',
        'password' => 'secret',
    ]);

    expect(cfaStep()->apply(cfaConsole(['Renamed', 'r@example.com', 'pw'])))->toBe(SetupOutcome::Applied)
        ->and(User::first()->full_name)->toBe('Renamed')
        ->and(User::first()->login)->toBe('r@example.com');
});

it('gives the account the role the permission gate actually checks', function () {
    // The name is the permission package's own, because that is what its
    // super-admin gate reads. Inventing one here would make an administrator
    // the gate does not recognise.
    cfaUsersTable();
    cfaRoleTables();

    expect(cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'pw'])))->toBe(SetupOutcome::Applied);

    $name = (string) config('permission-extended.super_admin_role', 'super-admin');

    expect(Role::where('name', $name)->exists())->toBeTrue()
        ->and(User::first()->hasRole($name))->toBeTrue();
});

it('still makes the account when the role tables are not there', function () {
    // The module works without roles, and an account with no role in an
    // application with no roles is complete rather than half-done.
    cfaUsersTable();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Solo', 'solo@example.com', 'pw'], $said)))->toBe(SetupOutcome::Applied)
        ->and(User::where('email', 'solo@example.com')->exists())->toBeTrue()
        ->and(implode("\n", $said))->toContain('not the `super-admin` role');
});

it('declines unattended rather than inventing a password', function () {
    // A generated password printed into a deploy log is a credential in a log,
    // and an empty one is an account anybody can use.
    cfaUsersTable();
    $said = [];

    expect(cfaStep()->apply(cfaConsole([], $said, interactive: false)))->toBe(SetupOutcome::Skipped)
        ->and(User::count())->toBe(0)
        ->and(implode("\n", $said))->toContain('without --no-interaction');
});

it('declines when an answer it cannot do without is empty', function () {
    cfaUsersTable();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Name', '', ''], $said)))->toBe(SetupOutcome::Skipped)
        ->and(User::count())->toBe(0)
        ->and(implode("\n", $said))->toContain('nothing was created');
});

it('fails rather than pretending, when the row cannot be written', function () {
    cfaUsersTable();
    User::create(['name' => 'Taken', 'email' => 'taken@example.com', 'password' => Hash::make('x')]);
    $said = [];

    // The same address twice, against a unique index.
    expect(cfaStep()->apply(cfaConsole(['Clash', 'taken@example.com', 'pw'], $said)))
        ->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('Could not create the account');
});

it('stands aside quietly when there is no database at all', function () {
    // The step above this one already says the connection is missing, in its own
    // words. Saying it twice would be noise, so this reports rather than throws
    // and leaves the explaining to the step that owns it.
    config()->set('database.connections.broken', [
        'driver' => 'sqlite',
        'database' => '/nonexistent/directory/database.sqlite',
        'prefix' => '',
    ]);
    config()->set('database.default', 'broken');
    DB::purge();

    expect(cfaStep()->state())->toBe(SetupState::Blocked)
        ->and(cfaStep()->summary())->toBe('no database connection yet');
});

it('says nothing about roles in an application that has none', function () {
    // The module works without `nyoncode/laravel-permission-extended`, so an
    // account with no role is complete rather than half-done — and the step must
    // not warn about a role nobody asked for.
    cfaUsersTable();
    config()->set('wire-module-users.roles', false);
    $said = [];

    expect(cfaStep()->summary())->toBe('create an account to sign in with')
        ->and(cfaStep()->apply(cfaConsole(['No roles', 'nr@example.com', 'pw'], $said)))
        ->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->not->toContain('role');
});
