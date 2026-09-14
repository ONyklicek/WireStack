<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Console\Concerns;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireModuleUsers\Support\Accounts;
use NyonCode\WireModuleUsers\Support\Teams;
use Throwable;

use function Laravel\Prompts\multiselect;

/**
 * The console half of giving an account roles: what was asked for, and saying
 * how each one went.
 *
 * Shared by `wire:user`, which gives roles to the account it has just made, and
 * `wire:assign-role`, which gives them to one that already exists. Deciding
 * whether a role *can* be given — the team it goes in, the role row that has to
 * exist — is {@see Accounts::assign()}, and nothing here second-guesses it.
 *
 * @mixin Command
 */
trait InteractsWithRoles
{
    /**
     * The roles named by `--role`, or picked when nothing was named.
     *
     * Never the super-admin: that is `--super-admin`, asked separately and given
     * globally by {@see grantSuperAdmin()}.
     *
     * @return array<int, string>
     */
    protected function requestedRoles(Accounts $accounts, bool $offer): array
    {
        /** @var array<int, string> $roles */
        $roles = array_values(array_filter((array) $this->option('role')));

        if ($roles === [] && $offer && $this->input->isInteractive() && $accounts->roles() !== []) {
            /** @var array<int, string> $roles */
            $roles = multiselect(
                label: 'Which roles should this account have?',
                options: array_combine($accounts->roles(), $accounts->roles()),
                hint: 'Space picks one, enter confirms.',
            );
        }

        return array_values(array_unique($roles));
    }

    /**
     * Make the account a super-admin, after saying what that means.
     *
     * Asked even when `--super-admin` was passed, where somebody is there to
     * answer: it can do everything, in every team, and a flag typed from shell
     * history is not the same as meaning it. Unattended, the flag is the answer.
     * Answers whether the account is a super-admin afterwards.
     */
    protected function grantSuperAdmin(Accounts $accounts, Model $user): bool
    {
        $email = $accounts->emailOf($user);

        if ($this->input->isInteractive()
            && ! $this->confirm("Make {$email} a super-admin? {$accounts->superAdminMeaning()}", true)) {
            return false;
        }

        try {
            $role = $accounts->makeSuperAdmin($user);
        } catch (Throwable $e) {
            $this->components->warn("Could not make it a super-admin: {$e->getMessage()}");

            return false;
        }

        if ($role === null) {
            $this->components->warn('This application has no super-admin role to give.');

            return false;
        }

        $this->components->twoColumnDetail('Super-admin', "<fg=green>{$role}</>");

        return true;
    }

    /**
     * Give each role, one line apiece. Answers whether every one of them was given.
     *
     * A role that cannot be given is said and passed over rather than thrown:
     * the account is real either way, and the others may well go through.
     *
     * @param  array<int, string>  $roles
     */
    protected function grantRoles(Accounts $accounts, Model $user, array $roles, int|string|null $team = null, bool $global = false): bool
    {
        $all = true;

        foreach ($roles as $role) {
            try {
                $global ? $accounts->assignGlobal($user, $role) : $accounts->assign($user, $role, $team);
                $this->components->twoColumnDetail('Role', "<fg=green>{$role}</>".($global && Teams::enabled() ? ', in every team' : ''));
            } catch (Throwable $e) {
                $this->components->warn("Could not give it `{$role}`: ".$e->getMessage());
                $all = false;
            }
        }

        return $all;
    }
}
