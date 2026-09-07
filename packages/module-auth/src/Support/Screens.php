<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Support;

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

/**
 * What this installation's sign-in surface actually has.
 *
 * Every question here is Fortify's to answer, and this class is where the
 * screens ask it. A "Forgot your password?" link under a login form that leads
 * to a route Fortify never registered is a 404 the application discovers from a
 * user, so the link is drawn from the same switch that creates the route.
 *
 * Features, not routes, for everything Fortify gates — `Features::enabled()` is
 * the declaration and the route is its consequence. The one exception is the way
 * out: `logout` is registered unconditionally by Fortify and unconditionally
 * absent without it, so `Route::has()` is the honest question there, and it is
 * asked at render because routes are not loaded when providers boot.
 */
final class Screens
{
    /** Whether an application takes new accounts through Fortify. */
    public static function canRegister(): bool
    {
        return Features::enabled(Features::registration());
    }

    /** Whether the "forgot your password" path exists to link to. */
    public static function canResetPassword(): bool
    {
        return Features::enabled(Features::resetPasswords());
    }

    /** Whether a new account has to confirm its address before it is let in. */
    public static function mustVerifyEmail(): bool
    {
        return Features::enabled(Features::emailVerification());
    }

    /** Whether a second factor can stand between the password and the panel. */
    public static function hasTwoFactor(): bool
    {
        return Features::enabled(Features::twoFactorAuthentication());
    }

    /**
     * Whether there is a route to sign out through.
     *
     * Asked of the router rather than of Fortify's features, because logout is
     * not one: it exists whenever Fortify registers its routes, and an
     * application that turned `Fortify::ignoreRoutes()` on has taken over the
     * naming. Either way this answers what a form can post to.
     */
    public static function canSignOut(): bool
    {
        return Route::has('logout');
    }
}
