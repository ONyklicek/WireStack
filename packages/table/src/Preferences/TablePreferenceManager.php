<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences;

use NyonCode\WireCore\Foundation\Preferences\Contracts\PreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;

/**
 * Which {@see PreferenceDriver} a *table* uses.
 *
 * The resolution rules are not here: they are
 * {@see PreferenceManager}'s, and this
 * is the one thing that is genuinely the table's — the config the answer comes
 * from. The store itself moved down to `wire-core` when a dashboard needed the
 * same per-user bag, and a second copy of "override, then swap, then config"
 * would have drifted from the first the moment either gained a rule.
 *
 * So `wire-table.preferences` stays the place an application configures a
 * table's store, and nothing an application wrote has to change.
 */
class TablePreferenceManager
{
    /** The config path holding `default`, `guest` and `drivers` for tables. */
    public const CONFIG_PREFIX = 'wire-table.preferences';

    /**
     * Resolve the effective driver for a table.
     *
     * @param  PreferenceDriver|null  $tableDriver  Per-table override (wins)
     * @param  bool  $authenticated  Whether a user is signed in (a guest picks the guest driver)
     */
    public static function resolve(?PreferenceDriver $tableDriver = null, bool $authenticated = true): PreferenceDriver
    {
        return PreferenceManager::resolve($tableDriver, $authenticated, self::CONFIG_PREFIX);
    }

    /**
     * Override the resolved driver globally. Pass null to clear.
     *
     * Forwarded rather than kept, so a test that swaps here also swaps the
     * dashboard's — which is what "this process stores nothing" has to mean.
     */
    public static function swap(?PreferenceDriver $driver): void
    {
        PreferenceManager::swap($driver);
    }
}
