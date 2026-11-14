<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireModuleUsers\Events\ImpersonationStarted;
use NyonCode\WireModuleUsers\Events\ImpersonationStopped;
use Throwable;

/**
 * Signing in as somebody else to see what they see — and back.
 *
 * Off unless the application turns it on (`wire-module-users.impersonation.enabled`),
 * and then only for a person holding the configured ability: being able to act
 * as any user is the most powerful thing these screens can hand out, so neither
 * half of the decision is a default.
 *
 * Even then, the account rules hold ({@see AccountGuard}): a super-admin is
 * only a super-admin's to sign in as, nobody signs in as themselves, and an
 * impersonation does not start another — the way back is always one step.
 *
 * The person who started it is kept in the session, not in a cookie or the
 * URL, and the guard's own `login()` does the switching both ways, so the
 * session id is regenerated at each step and everything an application hangs on
 * a login — remember tokens, the password hash `AuthenticateSession` checks —
 * moves with it. Each step dispatches an event for an audit trail to record.
 */
final class Impersonation
{
    /** Where the session keeps who started it. */
    public const SESSION_KEY = 'wire_users.impersonator';

    public static function enabled(): bool
    {
        return (bool) config('wire-module-users.impersonation.enabled', false);
    }

    /** The ability that grants it; null means nobody holds it. */
    public static function permission(): ?string
    {
        $permission = config('wire-module-users.impersonation.permission', 'users.impersonate');

        return is_string($permission) && $permission !== '' ? $permission : null;
    }

    /** Whether the person may sign in as this account now. */
    public static function mayImpersonate(Model $target, mixed $actor = null): bool
    {
        $actor ??= Auth::user();
        $permission = self::permission();

        if (! self::enabled() || $permission === null || ! $actor instanceof Model || self::active()) {
            return false;
        }

        if ($actor->getMorphClass() === $target->getMorphClass() && $actor->getKey() === $target->getKey()) {
            return false;
        }

        if (! AccountGuard::mayEdit($target, $actor)) {
            return false;
        }

        // A team's manager signs in as a member of the team they manage, never
        // as anybody else in the installation.
        if (AccountGuard::managesOneTeam($actor)) {
            $team = Teams::currentId($actor);

            if ($team === null || ! Teams::isMember($team, $target)) {
                return false;
            }
        }

        try {
            return Gate::forUser($actor)->allows($permission, $target);
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether the current session is somebody acting as somebody else. */
    public static function active(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /** The person who started the impersonation, while one is running. */
    public static function impersonator(): ?Authenticatable
    {
        $started = session(self::SESSION_KEY);

        if (! is_array($started) || ! isset($started['id'], $started['guard'])) {
            return null;
        }

        return Auth::guard((string) $started['guard'])->getProvider()->retrieveById($started['id']);
    }

    /**
     * Sign in as the account. Refused — 403 — for anyone {@see mayImpersonate()}
     * says no to, however the call was reached.
     */
    public static function start(Model $target): void
    {
        $actor = Auth::user();

        abort_unless($target instanceof Authenticatable && self::mayImpersonate($target, $actor), 403);

        $guard = self::guardName();

        session()->put(self::SESSION_KEY, ['id' => $actor->getAuthIdentifier(), 'guard' => $guard]);
        Auth::guard($guard)->login($target);

        event(new ImpersonationStarted($actor, $target));
    }

    /** Back to the account that started it. Nothing happens outside one. */
    public static function stop(): void
    {
        $impersonator = self::impersonator();
        $started = session(self::SESSION_KEY);
        $target = Auth::user();

        session()->forget(self::SESSION_KEY);

        if ($impersonator === null || ! is_array($started)) {
            return;
        }

        Auth::guard((string) $started['guard'])->login($impersonator);

        event(new ImpersonationStopped($impersonator, $target));
    }

    private static function guardName(): string
    {
        return (string) (Auth::getDefaultDriver() ?: 'web');
    }
}
