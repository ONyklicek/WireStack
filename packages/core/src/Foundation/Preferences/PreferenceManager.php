<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences;

use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\Drivers\NullPreferenceDriver;

/**
 * Resolves which {@see PreferenceDriver} a surface uses.
 *
 * Priority, highest first:
 *   1. A per-surface driver the caller passes in.
 *   2. A driver swapped in globally with {@see swap()} (mostly for tests).
 *   3. The configured driver — `<prefix>.default` for an authenticated user,
 *      `<prefix>.guest` for a guest, so a DB-backed default can still fall back
 *      to per-session memory for guests.
 *
 * ## Why the config prefix is a parameter
 *
 * Because each surface chooses its own store, and there is no single right
 * answer for an application: a table's column layout may be worth a database
 * row while a dashboard's is not, or the other way round. `wire-table` asks with
 * `wire-table.preferences`, and anything else asks with its own — one resolver,
 * one set of rules, and the choice stays where the surface is configured.
 *
 * Aliases resolve through `<prefix>.drivers`; an unknown alias, or a missing
 * config (an isolated unit test), falls back to the no-op
 * {@see NullPreferenceDriver}.
 */
class PreferenceManager
{
    private static ?PreferenceDriver $swapped = null;

    /**
     * Resolve the effective driver.
     *
     * @param  PreferenceDriver|null  $override  Per-surface driver (wins)
     * @param  bool  $authenticated  Whether a user is signed in (a guest picks the guest driver)
     * @param  string  $configPrefix  Config path holding `default`, `guest` and `drivers`
     */
    public static function resolve(
        ?PreferenceDriver $override = null,
        bool $authenticated = true,
        string $configPrefix = 'wire-core.preferences',
    ): PreferenceDriver {
        if ($override !== null) {
            return $override;
        }

        if (self::$swapped !== null) {
            return self::$swapped;
        }

        return self::fromConfig($authenticated, $configPrefix);
    }

    /**
     * Override the resolved driver globally (bypasses config). Pass null to
     * clear. Intended for tests and programmatic setups.
     *
     * Deliberately one switch for every surface rather than one per prefix: its
     * purpose is "this process stores nothing" or "this process stores here",
     * and a test that had to remember which surfaces it had swapped would be
     * the kind of bookkeeping the switch exists to avoid.
     */
    public static function swap(?PreferenceDriver $driver): void
    {
        self::$swapped = $driver;
    }

    private static function fromConfig(bool $authenticated, string $configPrefix): PreferenceDriver
    {
        $default = self::config($configPrefix.'.default', 'null');
        $alias = $authenticated
            ? $default
            : self::config($configPrefix.'.guest', $default);

        /** @var array<string, class-string<PreferenceDriver>> $drivers */
        $drivers = self::config($configPrefix.'.drivers', []);
        $class = $drivers[$alias] ?? NullPreferenceDriver::class;

        return app($class);
    }

    /**
     * Read config defensively so the manager still works when the framework
     * config is not booted (isolated unit tests).
     */
    private static function config(string $key, mixed $default): mixed
    {
        if (! function_exists('config')) {
            return $default;
        }

        return config($key, $default) ?? $default;
    }
}
