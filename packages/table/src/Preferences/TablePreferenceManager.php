<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences;

use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;
use NyonCode\WireTable\Preferences\Drivers\NullPreferenceDriver;

/**
 * Resolves which {@see TablePreferenceDriver} a table uses.
 *
 * Priority, highest first:
 *   1. A per-table driver passed to `Table::preferenceDriver()`.
 *   2. A driver swapped in globally with {@see swap()} (mostly for tests).
 *   3. The configured driver — `wire-table.preferences.default` for an
 *      authenticated user, `wire-table.preferences.guest` for a guest, so a
 *      DB-backed default can still fall back to per-session memory for guests.
 *
 * Aliases resolve through `wire-table.preferences.drivers`; unknown aliases
 * (or a missing config, e.g. in isolated unit tests) fall back to the no-op
 * {@see NullPreferenceDriver}.
 */
class TablePreferenceManager
{
    private static ?TablePreferenceDriver $swapped = null;

    /**
     * Resolve the effective driver.
     *
     * @param  TablePreferenceDriver|null  $tableDriver  Per-table override (wins)
     * @param  bool  $authenticated  Whether a user is signed in (guest picks the guest driver)
     */
    public static function resolve(?TablePreferenceDriver $tableDriver = null, bool $authenticated = true): TablePreferenceDriver
    {
        if ($tableDriver !== null) {
            return $tableDriver;
        }

        if (self::$swapped !== null) {
            return self::$swapped;
        }

        return self::fromConfig($authenticated);
    }

    /**
     * Override the resolved driver globally (bypasses config). Pass null to
     * clear. Intended for tests and programmatic setups.
     */
    public static function swap(?TablePreferenceDriver $driver): void
    {
        self::$swapped = $driver;
    }

    private static function fromConfig(bool $authenticated): TablePreferenceDriver
    {
        $default = self::config('wire-table.preferences.default', 'null');
        $alias = $authenticated
            ? $default
            : self::config('wire-table.preferences.guest', $default);

        /** @var array<string, class-string<TablePreferenceDriver>> $drivers */
        $drivers = self::config('wire-table.preferences.drivers', []);
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
