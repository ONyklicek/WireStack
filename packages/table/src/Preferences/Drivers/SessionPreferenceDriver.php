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
    public function load(string $tableKey, ?Authenticatable $user): array
    {
        $stored = Session::get($this->key($tableKey, $user), []);

        return is_array($stored) ? $stored : [];
    }

    public function save(string $tableKey, ?Authenticatable $user, array $preferences): void
    {
        Session::put($this->key($tableKey, $user), $preferences);
    }

    public function forget(string $tableKey, ?Authenticatable $user): void
    {
        Session::forget($this->key($tableKey, $user));
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
