<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;

/**
 * The two preconditions the guarded surfaces need, said once.
 *
 * A test about the user form or the two-factor card is not a test about the
 * guard in front of it, so it has to get past that guard before it can assert
 * anything. The tests that *are* about the guard deliberately do not call these.
 */
final class Access
{
    /**
     * Answer yes to every ability, the way a super-admin's `Gate::before` does.
     *
     * The nullable parameter is load-bearing, not decoration: Laravel skips a
     * `before` callback entirely for an unauthenticated request unless its first
     * parameter admits one (`Gate::callbackAllowsGuests()` requires a first
     * parameter and checks whether it is nullable). Written without it, this
     * silently grants nothing to exactly the tests that never sign anybody in.
     */
    public static function grantEveryAbility(): void
    {
        Gate::before(static fn (?Authenticatable $user): bool => true);
    }

    /**
     * Sign in somebody who may hand out every role and permission.
     *
     * A super-admin as `RoleGrants` recognises one — `hasGlobalRole()` answering
     * yes — without the tables a real account needs. For the tests about a form
     * or a page, which are not tests about escalation and would otherwise find
     * every option narrowed to what nobody signed in holds.
     */
    public static function actAsSuperAdmin(): void
    {
        // An authenticatable, authorisable model that is never saved: the Gate
        // callbacks the permission package registers type their user as one.
        $user = new class extends User
        {
            public function hasGlobalRole(mixed $role): bool
            {
                return true;
            }
        };

        auth()->setUser($user->forceFill(['id' => 0]));
    }

    /**
     * Put a fresh password confirmation in the session.
     *
     * The same key and clock Laravel's own `RequirePassword` reads, so a test
     * says "this person confirmed their password just now" in the one way the
     * application would recognise.
     */
    public static function confirmPassword(): void
    {
        session()->put('auth.password_confirmed_at', time());
    }

    /** Let a confirmation go stale without waiting out the timeout. */
    public static function expirePasswordConfirmation(): void
    {
        session()->put(
            'auth.password_confirmed_at',
            time() - (int) config('auth.password_timeout', 10800) - 60,
        );
    }
}
