<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * An account this module could make, and a role it could not give it.
 *
 * `RuntimeException` per ADR 0022: nothing about the arguments is wrong. The
 * account is real, the role name is a real role, and the reason it cannot be
 * granted is the state the application happens to be in — which is the
 * definition of a runtime failure rather than a bad argument.
 *
 * Thrown rather than swallowed, because the callers report it differently and
 * both of them keep the account: `wire:user` warns and still exits zero, the
 * installer's step warns and carries on. What neither can do is invent a
 * missing team.
 */
final class AccountException extends RuntimeException implements WireException
{
    /**
     * Roles are scoped to a team, and this account belongs to none.
     *
     * The permission package writes the team onto the pivot and makes the
     * column `NOT NULL`, so an unscoped assignment does not quietly become a
     * global role — it becomes `SQLSTATE[23000] … model_has_roles.team_id`,
     * which is a true statement about a database and no help at all to somebody
     * who has just installed an admin panel.
     */
    public static function roleNeedsATeam(string $role, string $email): self
    {
        return new self(
            // The command in backticks, and not last for that reason alone: Laravel's
            // warning component ends a message with a full stop, and a copied
            // `--role=super-admin.` names a role nobody has.
            "roles here are scoped to a team and {$email} is in none — put it in one, then run `php artisan wire:assign-role {$email} --role={$role}`"
        );
    }

    /**
     * A team was named, and the account is not a member of it.
     *
     * Refused rather than written: the permission package would store the role,
     * and it would be a role in a team the switcher never offers this person —
     * granted, and unreachable.
     */
    public static function notInTeam(string $email, int|string $team): self
    {
        return new self("{$email} is not a member of team {$team} — add them to it first, or name one of theirs");
    }

    /**
     * The super-admin was asked for as a role.
     *
     * It can do everything, in every team, so it is never one role among
     * others: with teams on, given in a team it would bypass nothing, and given
     * alongside "editor" it is too easy to hand out by accident.
     */
    public static function superAdminIsNotARole(string $role, string $email): self
    {
        return new self("`{$role}` can do everything, in every team, so it is not given as a role — run `php artisan wire:assign-role {$email} --super-admin`");
    }

    /**
     * The only super-admin there is, about to stop being one.
     *
     * Refused without being told twice: an installation with no super-admin has
     * nobody who may change the administrator role or put a super-admin back,
     * short of the database.
     */
    public static function lastSuperAdmin(string $email): self
    {
        return new self("{$email} is the only super-admin — make another one first, or pass --force if you mean to leave none");
    }

    /**
     * The fresh process that was to give the role did not finish.
     *
     * What it printed is the reason: that process is `wire:assign-role`, and it
     * already says what went wrong — a missing table, a model it could not
     * find — in its own words.
     */
    public static function roleProcessFailed(string $output): self
    {
        return new self($output !== '' ? $output : 'wire:assign-role did not finish');
    }
}
