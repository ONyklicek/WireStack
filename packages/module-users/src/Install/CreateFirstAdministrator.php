<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Install;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Process;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\Contracts\SetupStep;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WireModuleUsers\Console\WireUserCommand;
use NyonCode\WireModuleUsers\Exceptions\AccountException;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use Throwable;

/**
 * Somebody to sign in as.
 *
 * The gap nothing in this framework covered. Every package installed, every
 * migration run, the shell scaffolded, the routes registered — and then the
 * login screen, with no account behind it and no command anywhere in the stack
 * that made one. The documented answer was `php artisan tinker`.
 *
 * It belongs to this module and not to the installer that asks, because what a
 * user *is* here is the application's own model, its own column names
 * (`wire-module-users.fields`) and its own roles — three things `wire-suite` has
 * no business knowing. All three are {@see Accounts}, which is also what
 * {@see WireUserCommand} asks; between them, this one decides *when* to offer
 * and that one is simply asked.
 *
 * ## Only ever the first
 *
 * {@see state()} is Done the moment the table has a row in it. This creates the
 * account that gets you in; it is not a user-management command, and an
 * installer that offered to add another administrator on every run would be one
 * nobody could run twice safely. The second account is `php artisan wire:user`.
 *
 * ## The role, when roles arrived in this same run
 *
 * The roles step patches the user model on disk after this process loaded it,
 * so the in-process check says there are no roles and the account would be made
 * without one — a 403 on the users screen for the only person who can sign in.
 * {@see Roles::waitingForRestart()} names that state, and the role is then given
 * by `permission:assign-role` in a fresh PHP process, which reads the patched
 * file.
 */
final readonly class CreateFirstAdministrator implements SetupStep
{
    public function __construct(private Accounts $accounts) {}

    public function label(): string
    {
        return 'First administrator';
    }

    public function state(): SetupState
    {
        if (! $this->accounts->ready()) {
            return SetupState::Blocked;
        }

        return $this->accounts->any() ? SetupState::Done : SetupState::Pending;
    }

    public function summary(): string
    {
        if ($this->accounts->model() === null) {
            return 'no user model — see wire-module-users.model';
        }

        if (! $this->accounts->ready()) {
            // The step above this one says what a missing database is, in its
            // own words; saying it twice would be noise.
            return 'no users table yet';
        }

        if ($this->accounts->any()) {
            return 'an account already exists, so you can sign in';
        }

        // Short enough to leave the status column its room: Laravel's two-column
        // layout drops its leader dots rather than wrapping when a row will not
        // fit, and a cramped line in a column of neat ones is what the listing
        // was rebuilt to stop.
        return Roles::enabled()
            ? 'create an account, with the super-admin role'
            : 'create an account to sign in with';
    }

    public function apply(SetupConsole $console): SetupOutcome
    {
        if (! $console->isInteractive()) {
            // A generated password printed into a deploy log is a credential in
            // a log, and an empty one is an account anybody can use. Neither is
            // better than saying so and leaving it.
            $console->warn('Needs a name, an e-mail and a password — run wire:install again without --no-interaction.');

            return SetupOutcome::Skipped;
        }

        $name = $console->ask('Name', 'Administrator');
        $email = $console->ask('E-mail address');
        $password = $console->secret('Password');

        if ($email === '' || $password === '') {
            $console->warn('No e-mail or no password — nothing was created.');

            return SetupOutcome::Skipped;
        }

        try {
            $user = $this->accounts->create($name, $email, $password);
        } catch (Throwable $e) {
            $console->warn('Could not create the account: '.$e->getMessage());

            return SetupOutcome::Failed;
        }

        $console->note("Created {$email}.");

        try {
            $role = $this->accounts->makeSuperAdmin($user)
                ?? (Roles::waitingForRestart() ? $this->superAdminInAFreshProcess($user) : null);

            if ($role !== null) {
                $console->note("Gave it the `{$role}` role.");
            }
        } catch (Throwable $e) {
            // The account is made and usable; only the role is missing, and
            // that is a screen away rather than a reason to call the step
            // failed and leave somebody wondering whether the user exists.
            $console->warn("Created the account, but not the `{$this->accounts->superAdminRole()}` role: ".$e->getMessage());
        }

        return SetupOutcome::Applied;
    }

    /**
     * Give the super-admin role from a process that has loaded the patched model.
     *
     * Spatie's own `permission:assign-role`, rather than a command of this
     * module's: it finds or creates the role and assigns it through the trait,
     * which is everything {@see Accounts::assign()} does in-process. The team
     * scope is decided here, where the account is, and refused the same way.
     */
    private function superAdminInAFreshProcess(Model $user): string
    {
        $role = $this->accounts->superAdminRole();

        $command = [
            PHP_BINARY,
            'artisan',
            'permission:assign-role',
            $role,
            (string) $user->getKey(),
            (string) config('auth.defaults.guard', 'web'),
            $user::class,
        ];

        if ((bool) config('permission.teams')) {
            $team = Teams::currentId($user);

            if ($team === null) {
                throw AccountException::roleNeedsATeam($role);
            }

            $command[] = '--team-id='.$team;
        }

        $result = Process::path(base_path())->run($command);

        if (! $result->successful()) {
            throw AccountException::roleProcessFailed(trim($result->errorOutput().' '.$result->output()));
        }

        return $role;
    }

    public function package(): string
    {
        return 'nyoncode/wire-module-users';
    }

    public function sort(): int
    {
        // After the tables exist, and after the shell and the routes — this is
        // the last thing that has to be true before somebody can sign in.
        return 400;
    }
}
