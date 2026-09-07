<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

/**
 * Whether this application has two-factor authentication, and what it says
 * about the person signed in.
 *
 * **Laravel Fortify owns the feature**, and that is the whole design. The
 * secret, the TOTP window, the recovery codes, the challenge screen on the way
 * in and the rate limiting around it are a security surface with a maintained
 * owner; a panel that re-implemented them would own that surface without gaining
 * a feature. What is missing from Fortify is only the part it deliberately has
 * no opinion about — the screen — so this module supplies the card and calls
 * Fortify's own actions to do the work.
 *
 * Everything here is reached by name rather than by import: this package does
 * not require Fortify, so the class must load and answer "no" in an application
 * that has never heard of it. That is the same shape {@see Roles} uses for the
 * permission packages, for the same reason.
 */
final class TwoFactor
{
    public const ENABLE_ACTION = 'Laravel\\Fortify\\Actions\\EnableTwoFactorAuthentication';

    public const CONFIRM_ACTION = 'Laravel\\Fortify\\Actions\\ConfirmTwoFactorAuthentication';

    public const DISABLE_ACTION = 'Laravel\\Fortify\\Actions\\DisableTwoFactorAuthentication';

    public const GENERATE_CODES_ACTION = 'Laravel\\Fortify\\Actions\\GenerateNewRecoveryCodes';

    public const FEATURES = 'Laravel\\Fortify\\Features';

    /** Whether the two-factor card should be part of this installation. */
    public static function enabled(): bool
    {
        $setting = config('wire-module-users.two_factor', 'auto');

        if (is_bool($setting)) {
            return $setting;
        }

        return $setting === 'auto' && self::available();
    }

    /**
     * Whether Fortify is installed *and* its two-factor feature is switched on.
     *
     * Two conditions rather than one, and both were worth checking: Fortify is a
     * dependency of several things an application may have installed for other
     * reasons, and its two-factor feature is a line in `fortify.features` that
     * an application can leave out. Installed-but-off has to read as off, or the
     * card appears and its buttons throw.
     */
    public static function available(): bool
    {
        // Fortify is a dev dependency of this repository, so an application
        // without it is exactly the case a test here cannot stand in for. The
        // guard and the catch are for that application: no Fortify at all, or
        // one too old or too new to answer the feature question.
        // @codeCoverageIgnoreStart
        if (! class_exists(self::ENABLE_ACTION) || ! class_exists(self::FEATURES)) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        try {
            /** @var class-string $features */
            $features = self::FEATURES;

            return (bool) $features::enabled($features::twoFactorAuthentication());
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * Whether the person has finished setting it up.
     *
     * "Confirmed", not "has a secret": Fortify writes the secret the moment the
     * QR code is generated, so a user who opened the panel and walked away has
     * one and is not protected by it. `two_factor_confirmed_at` is the column
     * that means what the card's badge says.
     */
    public static function confirmed(?Authenticatable $user): bool
    {
        if ($user === null || ! self::enabled()) {
            return false;
        }

        // The application may not have run Fortify's migration, in which case
        // the attribute is simply absent — which is "not set up".
        $confirmedAt = self::attribute($user, 'two_factor_confirmed_at');

        if ($confirmedAt !== null) {
            return true;
        }

        // Fortify can be configured without the confirmation step
        // (`confirmTwoFactorAuthentication` off), and then a secret *is* the
        // whole of it.
        return ! self::confirmationRequired() && self::pending($user);
    }

    /** Whether a secret exists but has not been confirmed yet. */
    public static function pending(?Authenticatable $user): bool
    {
        if ($user === null || ! self::enabled()) {
            return false;
        }

        return self::attribute($user, 'two_factor_secret') !== null;
    }

    /**
     * Whether Fortify asks for a code before it counts the setup as done.
     *
     * Read off Fortify's own feature options rather than a config key copied
     * here: `confirm` is declared inside `Features::twoFactorAuthentication([…])`
     * in the application's `fortify.features`, so there is no flat key to read.
     */
    public static function confirmationRequired(): bool
    {
        if (! self::available()) {
            return true;
        }

        try {
            /** @var class-string $features */
            $features = self::FEATURES;

            return (bool) $features::optionEnabled($features::twoFactorAuthentication(), 'confirm');
            // @codeCoverageIgnoreStart — a Fortify that cannot answer; see available()
        } catch (Throwable) {
            return true;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * One attribute off the user, or null when the model has no such column.
     *
     * `getAttribute()` on a model without the column returns null, but a user
     * that is not an Eloquent model at all — a custom guard's user object — has
     * no `getAttribute()` to call.
     */
    private static function attribute(Authenticatable $user, string $name): mixed
    {
        if (! method_exists($user, 'getAttribute')) {
            return null;
        }

        try {
            return $user->getAttribute($name);
            // @codeCoverageIgnoreStart — a getAttribute() that throws is a model
            // this module cannot predict; absent reads the same as not set up.
        } catch (Throwable) {
            return null;
        }
        // @codeCoverageIgnoreEnd
    }
}
