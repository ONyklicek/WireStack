<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Console;

use Illuminate\Console\Command;
use NyonCode\WireModuleUsers\Console\Concerns\InteractsWithRoles;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan wire:assign-role` — roles for an account that already exists.
 *
 * {@see WireUserCommand} gives roles to the account it has just made; this one
 * gives them to an account made earlier, and never touches its name or password.
 *
 * ## The super-admin is not a role here
 *
 * It can do everything, in every team, so it is `--super-admin` — confirmed
 * where somebody can answer, given globally, and never combined with `--team`.
 * `--role=super-admin` is refused: with teams on it would be a role of one team
 * that bypasses nothing, and beside `--role=editor` it is too easy to type.
 *
 * ## Every other role is the account's team's
 *
 * Where roles are scoped to teams, a role goes in the team named by `--team`,
 * or the account's current team when none is named. A team the account is not
 * a member of is refused: the role would be stored where the switcher never
 * offers it.
 *
 *   php artisan wire:assign-role jane@example.com --super-admin
 *   php artisan wire:assign-role jane@example.com --role=editor --role=support
 *   php artisan wire:assign-role jane@example.com --role=editor --team=3
 *   php artisan wire:assign-role ada@example.com --role=admin --global
 *
 * ## `--global`, for the administrator
 *
 * A role given with `--global` counts in every team at once — the permission
 * package stores it outside any team — which is how the administrator role is
 * meant to be held, and never from a screen: only here, where the person typing
 * has the server. The administrator and team-manager roles are made with the
 * abilities of the user and role screens the first time they are given.
 */
#[AsCommand(name: 'wire:assign-role')]
class WireAssignRoleCommand extends Command
{
    use InteractsWithRoles;

    protected $signature = 'wire:assign-role
        {email? : The address the account signs in with}
        {--role=* : Roles to give the account, created where the application has none}
        {--team= : Where roles are scoped to teams, the team to give them in}
        {--global : Give the roles in every team at once — the administrator\'s way in}
        {--super-admin : Make the account a super-admin, who can do everything in every team}';

    protected $description = 'Give roles to an account that already exists.';

    public function handle(Accounts $accounts): int
    {
        if (! $accounts->ready()) {
            $this->components->error('No users to give a role to. Point wire-module-users.model at yours, and run php artisan migrate.');

            return self::FAILURE;
        }

        if (! Roles::enabled()) {
            $this->components->error('This application has no roles. Run php artisan wire:install to set them up.');

            return self::FAILURE;
        }

        if ($this->option('super-admin') && $this->option('team') !== null) {
            $this->components->error('A super-admin can do everything in every team, so it takes no --team.');

            return self::FAILURE;
        }

        if ($this->option('global') && $this->option('team') !== null) {
            $this->components->error('--global gives the roles in every team, so it takes no --team.');

            return self::FAILURE;
        }

        $email = $this->email();
        $user = $email === '' ? null : $accounts->find($email);

        if ($user === null) {
            $this->components->error($email === ''
                ? 'Name the account: php artisan wire:assign-role <e-mail> --role=…'
                : "No account signs in as {$email}. php artisan wire:user makes one.");

            return self::FAILURE;
        }

        $superAdmin = (bool) $this->option('super-admin');
        $roles = $this->requestedRoles($accounts, offer: ! $superAdmin);

        if ($roles === [] && ! $superAdmin) {
            $this->components->error('No role named. Pass --role=… or --super-admin.');

            return self::FAILURE;
        }

        $team = $this->team();
        $global = (bool) $this->option('global');

        if (($team !== null || $global) && ! Teams::enabled()) {
            $this->components->warn('Roles here are not scoped to teams, so '.($global ? '--global' : '--team').' changes nothing.');
            $team = null;
        }

        $granted = ! $superAdmin || $this->grantSuperAdmin($accounts, $user);

        $given = $global
            ? $this->grantRoles($accounts, $user, $roles, global: true)
            : $this->grantRoles($accounts, $user, $roles, $team);

        return $given && $granted ? self::SUCCESS : self::FAILURE;
    }

    /**
     * The address, from the argument or asked for — never guessed.
     */
    protected function email(): string
    {
        $given = $this->argument('email');

        if (is_string($given) && $given !== '') {
            return $given;
        }

        return $this->input->isInteractive() ? trim((string) $this->ask('E-mail address')) : '';
    }

    /**
     * The team named by `--team`, typed as the key a team model would carry.
     */
    protected function team(): int|string|null
    {
        $team = $this->option('team');

        if (! is_string($team) || $team === '') {
            return null;
        }

        return ctype_digit($team) ? (int) $team : $team;
    }
}
