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
 * `php artisan wire:user:role` — roles for an account that already exists.
 *
 * {@see WireUserCommand} gives roles to the account it has just made, and that
 * left no way to give one to an account made earlier. It mattered most in the
 * one place a person is told to do it: where roles are scoped to teams, a new
 * administrator belongs to no team, so the installer makes the account and
 * cannot give it a role — and the instruction it printed pointed at
 * `wire:user`, which makes a *new* account and refuses the existing address.
 *
 * ## The team is the account's own
 *
 * Where roles are scoped to teams, a role is given in the team named by
 * `--team`, or in the account's current team when none is named. A team the
 * account is not a member of is refused rather than written: the role would be
 * stored, and it would sit in a team the switcher never offers this person.
 * Membership is the application's own relation, so putting somebody in a team
 * is the application's to do first.
 *
 *   php artisan wire:user:role jane@example.com --admin
 *   php artisan wire:user:role jane@example.com --role=editor --role=support
 *   php artisan wire:user:role jane@example.com --admin --team=3
 */
#[AsCommand(name: 'wire:user:role')]
class WireUserRoleCommand extends Command
{
    use InteractsWithRoles;

    protected $signature = 'wire:user:role
        {email? : The address the account signs in with}
        {--admin : Give the account the super-admin role}
        {--role=* : Roles to give the account, created where the application has none}
        {--team= : Where roles are scoped to teams, the team to give them in}';

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

        $email = $this->email();
        $user = $email === '' ? null : $accounts->find($email);

        if ($user === null) {
            $this->components->error($email === ''
                ? 'Name the account: php artisan wire:user:role <e-mail> --admin'
                : "No account signs in as {$email}. php artisan wire:user makes one.");

            return self::FAILURE;
        }

        $roles = $this->requestedRoles($accounts);

        if ($roles === []) {
            $this->components->error('No role named. Pass --admin or --role=…');

            return self::FAILURE;
        }

        $team = $this->team();

        if ($team !== null && ! Teams::enabled()) {
            $this->components->warn('Roles here are not scoped to teams, so --team changes nothing.');
            $team = null;
        }

        return $this->grantRoles($accounts, $user, $roles, $team) ? self::SUCCESS : self::FAILURE;
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
