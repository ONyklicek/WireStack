<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Install\CreateFirstAdministrator;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Tests\Fixtures\PatchedOnDiskUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\SpatieOnlyUser;
use NyonCode\WireModuleUsers\Tests\Fixtures\User;
use NyonCode\WireModuleUsers\Tests\Support\Tables;
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
function cfaConsole(array $answers, array &$said = [], bool $interactive = true, bool $superAdmin = true): SetupConsole
{
    return new class($answers, $said, $interactive, $superAdmin) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(
            private array $answers,
            private array &$said,
            private bool $interactive,
            private bool $superAdmin,
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
            $this->said[] = $question;

            return str_contains($question, 'super-admin') ? $this->superAdmin : $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return (string) $default;
        }

        /**
         * @param  array<int|string, string>  $options
         * @param  array<int, int|string>  $default
         * @return array<int, int|string>
         */
        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
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
    // Resolved rather than constructed: what a user is here — the model, the
    // column names, the roles — is `Accounts`, which the command shares.
    return app(CreateFirstAdministrator::class);
}

it('is contributed by this module, so the installer never learns what a user is', function () {
    expect(SetupRegistry::instance()->all())->toContain(CreateFirstAdministrator::class);
});

it('is pending while nobody can sign in', function () {
    Tables::users();

    expect(cfaStep()->state())->toBe(SetupState::Pending)
        ->and(cfaStep()->summary())->toContain('create an account')
        ->and(cfaStep()->label())->toBe('First administrator')
        ->and(cfaStep()->sort())->toBe(400);
});

it('is done the moment there is an account, and never offers a second', function () {
    // Not a user-management command: an installer that offered to add another
    // administrator on every run is one nobody could run twice safely.
    Tables::users();
    User::create(['name' => 'Jane', 'email' => 'jane@example.com', 'password' => Hash::make('x')]);

    expect(cfaStep()->state())->toBe(SetupState::Done)
        ->and(cfaStep()->summary())->toBe('an account already exists, so you can sign in');
});

it('is blocked, not offered, before the users table exists', function () {
    expect(cfaStep()->state())->toBe(SetupState::Blocked)
        ->and(cfaStep()->summary())->toBe('no users table yet');
});

it('is blocked when the application has no user model to point at', function () {
    config()->set('wire-module-users.model', 'App\\Models\\NotHere');

    expect(cfaStep()->state())->toBe(SetupState::Blocked)
        ->and(cfaStep()->summary())->toContain('no user model');

    config()->set('wire-module-users.model', '');

    expect(cfaStep()->state())->toBe(SetupState::Blocked);
});

it('creates the account, with the password hashed', function () {
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Ondřej', 'o@example.com', 'hunter2hunter2', 'hunter2hunter2'], $said)))
        ->toBe(SetupOutcome::Applied);

    $user = User::first();

    expect($user->name)->toBe('Ondřej')
        ->and($user->email)->toBe('o@example.com')
        ->and($user->password)->not->toBe('hunter2hunter2')
        ->and(Hash::check('hunter2hunter2', $user->password))->toBeTrue()
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

    expect(cfaStep()->apply(cfaConsole(['Renamed', 'r@example.com', 'long-enough-pw', 'long-enough-pw'])))->toBe(SetupOutcome::Applied)
        ->and(User::first()->full_name)->toBe('Renamed')
        ->and(User::first()->login)->toBe('r@example.com');
});

it('asks, in full, and makes the account a super-admin the permission gate recognises', function () {
    // The name is the permission package's own, because that is what its gate
    // reads — and the question says what the answer means.
    Tables::users();
    Tables::roles();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'long-enough-pw', 'long-enough-pw'], $said)))->toBe(SetupOutcome::Applied);

    $name = (string) config('permission-extended.super_admin_role', 'super-admin');

    expect(Role::where('name', $name)->exists())->toBeTrue()
        ->and(User::first()->hasGlobalRole($name))->toBeTrue()
        ->and(implode("\n", $said))->toContain('Make boss@example.com a super-admin? It can do everything.')
        ->and(implode("\n", $said))->toContain('Made it a super-admin (`super-admin`).');
});

it('leaves the account an ordinary one when the answer is no', function () {
    Tables::users();
    Tables::roles();

    cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'long-enough-pw', 'long-enough-pw'], superAdmin: false));

    expect(User::first()->hasGlobalRole('super-admin'))->toBeFalse()
        ->and(Role::where('name', 'super-admin')->exists())->toBeFalse();
});

it('still makes the account when the role tables are not there', function () {
    // The module works without roles, and an account with no role in an
    // application with no roles is complete rather than half-done.
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Solo', 'solo@example.com', 'long-enough-pw', 'long-enough-pw'], $said)))->toBe(SetupOutcome::Applied)
        ->and(User::where('email', 'solo@example.com')->exists())->toBeTrue()
        ->and(implode("\n", $said))->toContain('did not make it a super-admin');
});

it('declines unattended rather than inventing a password', function () {
    // A generated password printed into a deploy log is a credential in a log,
    // and an empty one is an account anybody can use.
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole([], $said, interactive: false)))->toBe(SetupOutcome::Skipped)
        ->and(User::count())->toBe(0)
        ->and(implode("\n", $said))->toContain('without --no-interaction');
});

it('declines when an answer it cannot do without is empty', function () {
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Name', '', ''], $said)))->toBe(SetupOutcome::Skipped)
        ->and(User::count())->toBe(0)
        ->and(implode("\n", $said))->toContain('nothing was created');
});

it('fails rather than pretending, when the row cannot be written', function () {
    // A column the application requires and this step does not fill.
    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->string('tenant');
        $table->timestamps();
    });
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Clash', 'jane@example.com', 'long-enough-pw', 'long-enough-pw'], $said)))
        ->toBe(SetupOutcome::Failed)
        ->and(implode("\n", $said))->toContain('Could not create the account');
});

it('asks again for an address that is not one, or that already has an account', function () {
    Tables::users();
    User::create(['name' => 'Taken', 'email' => 'taken@example.com', 'password' => Hash::make('x')]);
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Ada', 'admin', 'taken@example.com', ' ada@example.com ', 'long-enough-pw', 'long-enough-pw'], $said, superAdmin: false)))
        ->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->toContain('Not an e-mail address: admin.')
        ->and(implode("\n", $said))->toContain('An account with taken@example.com already exists.')
        ->and(User::query()->where('email', 'ada@example.com')->exists())->toBeTrue();
});

it('creates nothing after three addresses that are not one', function () {
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Ada', 'a', 'b', 'c', 'long-enough-pw', 'long-enough-pw'], $said)))
        ->toBe(SetupOutcome::Skipped)
        ->and(User::query()->count())->toBe(0);
});

it('stands aside quietly when there is no database at all', function () {
    // Reported rather than thrown, and in the same words as a missing table:
    // the step above this one owns what "no database" means, and saying it
    // twice in one listing was the thing worth avoiding.
    config()->set('database.connections.broken', [
        'driver' => 'sqlite',
        'database' => '/nonexistent/directory/database.sqlite',
        'prefix' => '',
    ]);
    config()->set('database.default', 'broken');
    DB::purge();

    expect(cfaStep()->state())->toBe(SetupState::Blocked)
        ->and(cfaStep()->summary())->toBe('no users table yet');
});

it('says nothing about roles in an application that has none', function () {
    // The module works without `nyoncode/laravel-permission-extended`, so an
    // account with no role is complete rather than half-done — and the step must
    // not warn about a role nobody asked for.
    Tables::users();
    config()->set('wire-module-users.roles', false);
    $said = [];

    expect(cfaStep()->summary())->toBe('create an account to sign in with')
        ->and(cfaStep()->apply(cfaConsole(['No roles', 'nr@example.com', 'long-enough-pw', 'long-enough-pw'], $said)))
        ->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->not->toContain('role');
});

it('belongs to its own package, so unticking that package skips it', function () {
    // What the first half of the installer was told, the second half obeys.
    expect(cfaStep()->package())->toBe('nyoncode/wire-module-users');
});

// ---------------------------------------------------------------------------
// Roles that arrived in this same run
// ---------------------------------------------------------------------------

it('knows a model patched on disk after this process loaded it', function () {
    config()->set('wire-module-users.model', PatchedOnDiskUser::class);

    expect(Roles::available())->toBeFalse()
        ->and(Roles::waitingForRestart())->toBeTrue();
});

it('is not waiting on a model that already has roles, has none coming, or has bare Spatie', function () {
    config()->set('wire-module-users.model', User::class);
    expect(Roles::waitingForRestart())->toBeFalse();

    config()->set('wire-module-users.model', SpatieOnlyUser::class);
    expect(Roles::waitingForRestart())->toBeFalse();

    config()->set('wire-module-users.model', PatchedOnDiskUser::class);
    config()->set('wire-module-users.roles', false);
    expect(Roles::waitingForRestart())->toBeFalse();
});

it('makes the first administrator a super-admin from a fresh process when roles arrived in this run', function () {
    // `permission-extended:install` patched the model after boot, so the
    // in-process check sees no roles. Made without one, the only account that
    // can sign in got a 403 on the users screen — measured in a real install.
    // The fresh process is the same command a person would run.
    config()->set('wire-module-users.model', PatchedOnDiskUser::class);
    Tables::users();
    Process::fake();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'long-enough-pw', 'long-enough-pw'], $said)))->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->toContain('Made it a super-admin');

    Process::assertRan(fn ($process): bool => array_slice((array) $process->command, 1) === [
        'artisan',
        'wire:assign-role',
        'boss@example.com',
        '--super-admin',
        '--no-interaction',
    ]);
});

it('says the super-admin is missing when the fresh process fails', function () {
    config()->set('wire-module-users.model', PatchedOnDiskUser::class);
    Tables::users();
    Process::fake(['*' => Process::result(errorOutput: 'no roles table', exitCode: 1)]);
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'long-enough-pw', 'long-enough-pw'], $said)))->toBe(SetupOutcome::Applied)
        ->and(PatchedOnDiskUser::query()->count())->toBe(1)
        ->and(implode("\n", $said))->toContain('did not make it a super-admin: no roles table');
});

it('still names the command when the fresh process fails without a word', function () {
    config()->set('wire-module-users.model', PatchedOnDiskUser::class);
    Tables::users();
    Process::fake(['*' => Process::result(exitCode: 1)]);
    $said = [];

    cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'long-enough-pw', 'long-enough-pw'], $said));

    expect(implode("\n", $said))->toContain('wire:assign-role did not finish');
});

it('offers no super-admin where the application switched it off', function () {
    Tables::users();
    Tables::roles();
    config()->set('permission-extended.super_admin_role', null);
    $said = [];

    cfaStep()->apply(cfaConsole(['Boss', 'boss@example.com', 'long-enough-pw', 'long-enough-pw'], $said));

    expect(implode("\n", $said))->not->toContain('super-admin');
});

it('asks again for a password the application\'s policy refuses, or one typed differently twice', function () {
    // `Password::defaults()`, the rule the profile screen holds a new password
    // to — and twice, because a typo in a hidden answer is an installation
    // nobody can sign in to.
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Ada', 'ada@example.com', 'pw', 'long-enough-pw', 'long-enough-typo', 'long-enough-pw', 'long-enough-pw'], $said, superAdmin: false)))
        ->toBe(SetupOutcome::Applied)
        ->and(implode("\n", $said))->toContain('at least 8 characters.')
        ->and(implode("\n", $said))->toContain('The two passwords are not the same.')
        ->and(Hash::check('long-enough-pw', User::query()->firstOrFail()->password))->toBeTrue();
});

it('creates nothing after three passwords it cannot use', function () {
    Tables::users();
    $said = [];

    expect(cfaStep()->apply(cfaConsole(['Ada', 'ada@example.com', 'a', 'b', 'c'], $said)))
        ->toBe(SetupOutcome::Skipped)
        ->and(User::query()->count())->toBe(0)
        ->and(implode("\n", $said))->toContain('nothing was created');
});
