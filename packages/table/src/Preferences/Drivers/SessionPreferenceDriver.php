<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences\Drivers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Session;
use NyonCode\WireTable\Preferences\Contracts\TablePreferenceDriver;

/**
 * Session-backed preferences: no database, no migration.
 *
 * Preferences live in the user's session, keyed by the table key and the user
 * identifier (or `guest` when unauthenticated). Ideal for guests and apps that
 * do not want a `table_preferences` table; the layout survives reloads for as
 * long as the session lives.
 */
class SessionPreferenceDriver implements TablePreferenceDriver
{
    public function load(string $tableKey, ?Authenticatable $user, ?string $view = null): array
    {
        $stored = $view === null
            ? Session::get($this->key($tableKey, $user), [])
            : ($this->savedViews($tableKey, $user)[$view] ?? []);

        return is_array($stored) ? $stored : [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences, ?string $view = null): void
    {
        if ($view === null) {
            Session::put($this->key($tableKey, $user), $preferences);

            return;
        }

        $views = $this->savedViews($tableKey, $user);
        $views[$view] = $preferences;

        Session::put($this->viewsKey($tableKey, $user), $views);
    }

    public function forget(string $tableKey, ?Authenticatable $user, ?string $view = null): void
    {
        if ($view === null) {
            Session::forget($this->key($tableKey, $user));

            return;
        }

        $views = $this->savedViews($tableKey, $user);
        unset($views[$view]);

        Session::put($this->viewsKey($tableKey, $user), $views);
    }

    public function views(string $tableKey, ?Authenticatable $user): array
    {
        return array_keys($this->savedViews($tableKey, $user));
    }

    /**
     * Every named view for a table + user, as `name => bag`.
     *
     * The names are array keys, never part of the session key, and the named
     * views sit under a root of their own. Both halves of that are load-bearing,
     * because Session::put() reads dots as nesting: hanging a view off the
     * current layout's key wrote it INSIDE that layout's bag, and a view named
     * "Q1.2026" would have done the same thing one level further down.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function savedViews(string $tableKey, ?Authenticatable $user): array
    {
        $stored = Session::get($this->viewsKey($tableKey, $user), []);

        return is_array($stored) ? $stored : [];
    }

    protected function viewsKey(string $tableKey, ?Authenticatable $user): string
    {
        $userId = $user?->getAuthIdentifier() ?? 'guest';

        return "wire-table.savedViews.{$userId}.{$tableKey}";
    }

    /**
     * Namespaced session key, scoped to the user so two accounts sharing a
     * browser session never see each other's layout.
     */
    protected function key(string $tableKey, ?Authenticatable $user): string
    {
        $userId = $user?->getAuthIdentifier() ?? 'guest';

        return "wire-table.preferences.{$userId}.{$tableKey}";
    }
}
