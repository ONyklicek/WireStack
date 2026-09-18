<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Roles;
use NyonCode\WireModuleUsers\Support\Teams;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

/**
 * `php artisan wire:revoke-role` — take roles away from an account.
 *
 * The other half of {@see WireAssignRoleCommand}, and the only way to take back
 * what only the command line gives: a global role, or the super-admin. The
 * screens never offer either, so without this an administrator made with
 * `--global` stayed one until somebody opened the database.
 *
 * The last super-admin is refused unless `--force` says so: an installation with
 * no super-admin has nobody who may change the administrator role or make a
 * super-admin again from a screen — only from here.
 *
 *   php artisan wire:revoke-role mia@example.com --role=team-admin --team=3
 *   php artisan wire:revoke-role ada@example.com --role=admin --global
 *   php artisan wire:revoke-role root@example.com --super-admin
 */
#[AsCommand(name: 'wire:revoke-role')]
class WireRevokeRoleCommand extends Command
{
    protected $signature = 'wire:revoke-role
        {email : The address the account signs in with}
        {--role=* : Roles to take away}
        {--team= : Where roles are scoped to teams, the team to take them from}
        {--global : Take roles the account holds in every team}
        {--super-admin : Stop the account being a super-admin}
        {--force : Take the super-admin from the last account that has it}';

    protected $description = 'Take roles away from an account.';

    public function handle(Accounts $accounts): int
    {
        if (! $accounts->ready() || ! Roles::enabled()) {
            $this->components->error('This application has no accounts with roles to take them from.');

            return self::FAILURE;
        }

        if ($this->option('global') && $this->option('team') !== null) {
            $this->components->error('--global takes roles held in every team, so it takes no --team.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $user = $accounts->find($email);

        if ($user === null) {
            $this->components->error("No account signs in as {$email}.");

            return self::FAILURE;
        }

        /** @var array<int, string> $roles */
        $roles = array_values(array_unique(array_filter((array) $this->option('role'))));
        $superAdmin = (bool) $this->option('super-admin');

        if ($roles === [] && ! $superAdmin) {
            $this->components->error('No role named. Pass --role=… or --super-admin.');

            return self::FAILURE;
        }

        $ok = true;

        if ($superAdmin) {
            $ok = $this->attempt($email, 'Super-admin', fn (): bool => $accounts->revokeSuperAdmin($user, (bool) $this->option('force')));
        }

        foreach ($roles as $role) {
            $ok = $this->attempt($email, $role, fn (): bool => $this->option('global')
                ? $accounts->revokeGlobal($user, $role)
                : $accounts->revoke($user, $role, $this->team())) && $ok;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Take one thing away and say how it went. Answers whether nothing went wrong.
     *
     * @param  callable(): bool  $revoke  Whether the account had it.
     */
    protected function attempt(string $email, string $what, callable $revoke): bool
    {
        try {
            $revoke()
                ? $this->components->twoColumnDetail($what, '<fg=yellow>taken away</>')
                : $this->components->twoColumnDetail($what, "<fg=gray>{$email} did not have it</>");

            return true;
        } catch (Throwable $e) {
            $this->components->warn("Could not take `{$what}`: ".$e->getMessage());

            return false;
        }
    }

    /**
     * The team named by `--team`, typed as the key a team model would carry.
     */
    protected function team(): int|string|null
    {
        $team = $this->option('team');

        if (! is_string($team) || $team === '' || ! Teams::enabled()) {
            return null;
        }

        return ctype_digit($team) ? (int) $team : $team;
    }
}
