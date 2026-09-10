<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Illuminate\Support\Facades\Route;

/**
 * Laravel's confirmed-password window, for a surface that is not a route.
 *
 * `password.confirm` is HTTP middleware, and it works because a request passes
 * through it on the way to a controller. A Livewire card does not: its buttons
 * arrive as messages on an already-authorised component, so an action reached
 * from a page guarded by `auth` alone is reached with no confirmation at all —
 * even when the same action behind its own route would have demanded one.
 *
 * That gap is not theoretical. Fortify guards every two-factor and passkey
 * management route with `password.confirm` by default, and a panel that drives
 * those same actions out of the container reaches them *around* the guard.
 * Somebody holding a borrowed session could turn a second factor off without
 * ever knowing the password it protects.
 *
 * So this reads the same session key Laravel's own `RequirePassword` writes and
 * reads — `auth.password_confirmed_at`, against `auth.password_timeout` — rather
 * than inventing a second notion of "recently confirmed". One window, one
 * timeout, one place an application configures it: confirm a password anywhere
 * in the application and every surface using this trait agrees that you did.
 *
 * It deliberately does **not** ask for a password itself. A second password
 * prompt written here would be a second thing to get wrong, and it would not
 * refresh the window that the rest of the application reads.
 */
trait InteractsWithPasswordConfirmation
{
    /**
     * Whether the password was confirmed recently enough to count.
     *
     * The same arithmetic as `RequirePassword::shouldConfirmPassword()`, and the
     * same defaults: a missing timestamp is a zero, which is always stale, so
     * "never confirmed" and "confirmed too long ago" answer alike.
     */
    protected function hasConfirmedPasswordRecently(): bool
    {
        $confirmedAt = (int) session('auth.password_confirmed_at', 0);

        if ($confirmedAt <= 0) {
            return false;
        }

        $timeout = (int) config('auth.password_timeout', 10800);

        return (time() - $confirmedAt) <= $timeout;
    }

    /**
     * The screen that takes a password, or null when nothing serves one.
     *
     * Null is not "let them through" — see {@see ensurePasswordConfirmed()}.
     * It means the application has no confirmation screen to send anybody to,
     * which is a reason to refuse rather than a reason to proceed.
     */
    protected function passwordConfirmationUrl(): ?string
    {
        return Route::has('password.confirm') ? route('password.confirm') : null;
    }

    /**
     * Refuse unless the password was confirmed recently; send them to confirm it.
     *
     * Returns true when the caller may go on. On false the caller must stop
     * immediately and do nothing else — a redirect queued on a Livewire
     * component does not interrupt the method that queued it, so a `return` is
     * the only thing that actually stops the action.
     *
     * Fails closed when there is no confirmation screen: a surface that cannot
     * ask for a password is a surface that cannot be satisfied, and letting the
     * action through would be the exact bypass this trait exists to close.
     */
    protected function ensurePasswordConfirmed(): bool
    {
        if ($this->hasConfirmedPasswordRecently()) {
            return true;
        }

        $url = $this->passwordConfirmationUrl();

        // Where to come back to, by the same key Laravel's own redirect-guest
        // path uses, so the confirmation screen returns the person to the card
        // they pressed a button on rather than to a dashboard.
        if ($url !== null && method_exists($this, 'redirect')) {
            session()->put('url.intended', url()->current());

            $this->redirect($url);
        }

        return false;
    }
}
