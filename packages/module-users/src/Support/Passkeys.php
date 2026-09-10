<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Whether this application has passkeys, and which ones the signed-in person has.
 *
 * **Laravel owns the feature**, exactly as it owns two-factor: `laravel/passkeys`
 * behind Fortify's `Features::passkeys()` ships the WebAuthn ceremony, the
 * credential rows, the routes and the browser client. What is missing from it is
 * the same thing that was missing from two-factor — a screen — so this module
 * supplies the card and calls the package's own routes and actions.
 *
 * Reached by name rather than by import, the way {@see TwoFactor} and {@see Roles}
 * are: this package requires neither Fortify nor the passkeys package, so the
 * class has to load and answer "no" in an application that has never heard of
 * either.
 */
final class Passkeys
{
    public const FEATURES = 'Laravel\\Fortify\\Features';

    public const DELETE_ACTION = 'Laravel\\Passkeys\\Actions\\DeletePasskey';

    public const PASSKEY_MODEL = 'Laravel\\Passkeys\\Passkey';

    /** Whether the passkey card should be part of this installation. */
    public static function enabled(): bool
    {
        $setting = config('wire-module-users.passkeys', 'auto');

        if (is_bool($setting)) {
            return $setting;
        }

        return $setting === 'auto' && self::available();
    }

    /**
     * Whether the packages are installed *and* Fortify's passkey feature is on.
     *
     * Both, and for the reason two-factor checks both: `laravel/passkeys` is a
     * dependency of Fortify, so it is present in applications that have never
     * enabled a passkey — and its routes exist only where the feature does.
     * Installed-but-off has to read as off, or the card renders buttons that post
     * into a 404.
     */
    public static function available(): bool
    {
        // Fortify and the passkeys package are dev dependencies of this
        // repository, so an application with neither is the one case a test here
        // cannot stand in for.
        // @codeCoverageIgnoreStart
        if (! class_exists(self::FEATURES) || ! class_exists(self::PASSKEY_MODEL)) {
            return false;
        }
        // @codeCoverageIgnoreEnd

        try {
            /** @var class-string $features */
            $features = self::FEATURES;

            return (bool) $features::enabled($features::passkeys());
            // @codeCoverageIgnoreStart
        } catch (Throwable) {
            return false;
        }
        // @codeCoverageIgnoreEnd
    }

    /**
     * The passkeys this person has registered, newest first.
     *
     * Empty for a user model without the package's trait, which is the state an
     * application is in between switching the feature on and adding the two lines
     * to its model — the card then says "none yet" rather than raising, and the
     * card's own hint names the trait.
     */
    public static function forUser(?Authenticatable $user): Collection
    {
        /** @var Collection<int, Model> $empty */
        $empty = new Collection;

        if ($user === null || ! self::enabled() || ! method_exists($user, 'passkeys')) {
            return $empty;
        }

        /** @var Collection<int, Model> $passkeys */
        $passkeys = $user->passkeys()->latest()->get();

        return $passkeys;
    }

    /** Whether the model can hold passkeys at all — the trait, in one question. */
    public static function usable(?Authenticatable $user): bool
    {
        return $user !== null && self::enabled() && method_exists($user, 'passkeys');
    }
}
