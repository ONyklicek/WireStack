<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Console\Concerns;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleUsers\Support\Accounts;
use Throwable;

use function Laravel\Prompts\multiselect;

/**
 * The console half of giving an account roles: what was asked for, and saying
 * how each one went.
 *
 * Shared by `wire:user`, which gives roles to the account it has just made, and
 * `wire:user:role`, which gives them to one that already exists. Deciding
 * whether a role *can* be given — the team it goes in, the role row that has to
 * exist — is {@see Accounts::assign()}, and nothing here second-guesses it.
 *
 * @mixin Command
 */
trait InteractsWithRoles
{
    /**
     * The roles named by `--role` and `--admin`, or picked when nothing was named.
     *
     * @param  array<int, string>  $preselected  Ticked when the list is offered.
     * @return array<int, string>
     */
    protected function requestedRoles(Accounts $accounts, array $preselected = []): array
    {
        /** @var array<int, string> $roles */
        $roles = array_values(array_filter((array) $this->option('role')));

        if ($this->option('admin')) {
            $roles[] = $accounts->superAdminRole();
        }

        if ($roles === [] && $this->input->isInteractive()) {
            $roles = $this->pickRoles($accounts, $preselected);
        }

        return array_values(array_unique($roles));
    }

    /**
     * Offer the roles this application already has, and super-admin beside them.
     *
     * @param  array<int, string>  $preselected
     * @return array<int, string>
     */
    protected function pickRoles(Accounts $accounts, array $preselected): array
    {
        $superAdmin = $accounts->superAdminRole();
        $available = $accounts->roles();

        if (! in_array($superAdmin, $available, true)) {
            $available[] = $superAdmin;
        }

        /** @var array<int, string> $picked */
        $picked = multiselect(
            label: 'Which roles should this account have?',
            options: array_combine($available, $available),
            default: $preselected,
            hint: 'Space picks one, enter confirms.',
        );

        return $picked;
    }

    /**
     * Give each role, one line apiece. Answers whether every one of them was given.
     *
     * A role that cannot be given is said and passed over rather than thrown:
     * the account is real either way, and the others may well go through.
     *
     * @param  array<int, string>  $roles
     */
    protected function grantRoles(Accounts $accounts, Model $user, array $roles, int|string|null $team = null): bool
    {
        $all = true;

        foreach ($roles as $role) {
            try {
                $accounts->assign($user, $role, $team);
                $this->components->twoColumnDetail('Role', "<fg=green>{$role}</>");
            } catch (Throwable $e) {
                $this->components->warn("Could not give it `{$role}`: ".$e->getMessage());
                $all = false;
            }
        }

        return $all;
    }
}
