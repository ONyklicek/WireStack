<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Contracts;

/**
 * A user model that decides for itself whether it wants a mailed second factor.
 *
 * The alternative was a column, and a package writing a migration onto an
 * application's `users` table is a package deciding what that table looks like.
 * This asks instead, and an application answers from wherever the answer lives —
 * a column it owns, a preference row, a role, a policy about staff accounts.
 *
 *     class User extends Authenticatable implements ReceivesLoginCodes
 *     {
 *         public function wantsLoginCode(): bool
 *         {
 *             return $this->two_factor_by_mail;
 *         }
 *     }
 *
 * A model that does not implement this is not opted out: the config switch
 * answers for everyone (ADR 0037 §4), which is what makes turning the flow on a
 * one-line change for an application that wants it everywhere.
 */
interface ReceivesLoginCodes
{
    /** Whether this user should be mailed a code after their password. */
    public function wantsLoginCode(): bool;
}
