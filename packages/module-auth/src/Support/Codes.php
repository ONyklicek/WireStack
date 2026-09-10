<?php

declare(strict_types=1);

namespace NyonCode\WireModuleAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NyonCode\WireModuleAuth\Actions\RedirectIfCodeRequired;
use NyonCode\WireModuleAuth\Contracts\ReceivesLoginCodes;

/**
 * Which of the four code flows this installation actually has.
 *
 * The companion to {@see Screens}, and it answers the same shape of question for
 * the same reason: a link to a screen whose route was never registered is a 404
 * an application finds out about from a user. Every flow is off until it is
 * switched on (ADR 0037 §3), so the answer here is what decides whether a route
 * exists, whether a link is drawn, and what `php artisan about` reports.
 */
final class Codes
{
    /** Whether anybody can sign in with a mailed code instead of a password. */
    public static function login(): bool
    {
        return self::enabled('login');
    }

    /**
     * Whether a mailed code can stand between a right password and the panel.
     *
     * Two switches, and both have to be on. The pipe that sends the code is
     * bound to a contract Fortify only puts in its login pipeline when the
     * two-factor feature is enabled (ADR 0037 §5) — so with the feature off this
     * is a flow that would silently never run, and saying so here is what keeps
     * that out of `about`, out of the installer's report, and out of a
     * production sign-in.
     */
    public static function secondFactor(): bool
    {
        return self::enabled('second_factor') && Features::enabled(Features::twoFactorAuthentication());
    }

    /** Whether an address can be confirmed by typing a code instead of following a link. */
    public static function verifyEmail(): bool
    {
        return self::enabled('verify_email') && Screens::mustVerifyEmail();
    }

    /** Whether the reset mail carries a code instead of a link. */
    public static function resetPassword(): bool
    {
        return self::enabled('reset_password') && Screens::canResetPassword();
    }

    /**
     * Whether *this* user is one the second factor is mailed to.
     *
     * The model's answer first, config's second (ADR 0037 §4). What is not asked
     * here is the authenticator app: a user with a confirmed TOTP secret is
     * Fortify's to challenge, and that check stays in
     * {@see RedirectIfCodeRequired} beside the
     * inherited one it has to agree with.
     */
    public static function wantedBy(?Authenticatable $user): bool
    {
        if ($user === null || ! self::secondFactor()) {
            return false;
        }

        return $user instanceof ReceivesLoginCodes
            ? $user->wantsLoginCode()
            : true;
    }

    /**
     * Whether the second factor is switched on but cannot run.
     *
     * The one state worth reporting on its own: config says yes, Fortify's
     * feature says no, and the consequence is a sign-in that quietly asks for
     * nothing. The installer and `about` both say this out loud rather than
     * printing "off" for something an application believes it turned on.
     */
    public static function secondFactorIsStranded(): bool
    {
        return self::enabled('second_factor') && ! Features::enabled(Features::twoFactorAuthentication());
    }

    /**
     * Whether Fortify would challenge this user for an authenticator code.
     *
     * The same three questions Fortify's own pipe asks, in one place because two
     * flows have to agree with it: the pipe inherits the answer, and the
     * passwordless screens have to know that a code to an inbox does not open a
     * session for somebody who set up a second factor (ADR 0037 §2).
     *
     * `confirmsTwoFactorAuthentication()` is the difference between "a secret
     * was generated" and "the app was proven to work" — a user who never
     * finished the setup would otherwise be locked behind a challenge they
     * cannot answer.
     */
    public static function usesAuthenticatorApp(Authenticatable $user): bool
    {
        if (! Features::enabled(Features::twoFactorAuthentication())) {
            return false;
        }

        if (! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return false;
        }

        if (! isset($user->two_factor_secret)) {
            return false;
        }

        return ! Fortify::confirmsTwoFactorAuthentication() || $user->two_factor_confirmed_at !== null;
    }

    /**
     * What a code is filed under.
     *
     * Two shapes, because the flows start from two different things. A sign-in
     * that has not happened yet knows only an address, and it is normalised —
     * `Ann@Example.com` typed on the second screen has to find the code mailed
     * for `ann@example.com` typed on the first. A sign-in whose password was
     * already right knows the user, and the key is the better identifier there:
     * it does not change when somebody edits their address mid-flow, and it is
     * not a value anybody types.
     */
    public static function identifierFor(Authenticatable|string $subject): string
    {
        return $subject instanceof Authenticatable
            ? (string) $subject->getAuthIdentifier()
            : Str::lower(trim($subject));
    }

    /** Whether any flow at all is on — what decides if the routes are registered. */
    public static function any(): bool
    {
        return self::login() || self::secondFactor() || self::verifyEmail() || self::resetPassword();
    }

    private static function enabled(string $flow): bool
    {
        return (bool) config('wire-module-auth.codes.'.$flow, false);
    }
}
