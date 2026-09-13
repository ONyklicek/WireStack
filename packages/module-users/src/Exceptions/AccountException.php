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
    public static function roleNeedsATeam(string $role): self
    {
        return new self(
            "roles here are scoped to a team and this account is in none — put it in one, then: php artisan wire:user --role={$role}"
        );
    }
}
