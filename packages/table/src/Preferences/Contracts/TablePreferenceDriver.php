<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Preferences\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireTable\Preferences\TablePreferenceManager;

/**
 * Contract for pluggable per-user table preference stores.
 *
 * A driver persists a small bag of UI preferences (currently the hidden-column
 * set) keyed by a stable table key and the current user, so a user's column
 * layout survives page reloads. Implement this to back preferences with any
 * store — the database, the session, a cache, an external service — and select
 * it via `config('wire-table.preferences.default')` or per table with
 * `Table::preferenceDriver()`.
 *
 * The preferences bag is an open, JSON-serializable map. Today it holds
 * `['columns' => ['hidden' => string[]]]`; keep unknown keys intact so future
 * preferences (sort, page size) can be added without a contract change.
 *
 * @see TablePreferenceManager
 */
interface TablePreferenceDriver
{
    /**
     * Load the stored preferences for a table + user.
     *
     * Return an empty array when nothing has been saved yet (the table then
     * keeps its configured defaults); return the stored bag otherwise.
     *
     * @param  string  $tableKey  Stable identifier from Table::rememberColumns()
     * @param  Authenticatable|null  $user  The current user (null for a guest)
     * @return array<string, mixed>
     */
    public function load(string $tableKey, ?Authenticatable $user): array;

    /**
     * Persist the preferences for a table + user (create or replace).
     *
     * @param  array<string, mixed>  $preferences
     */
    public function save(string $tableKey, ?Authenticatable $user, array $preferences): void;

    /**
     * Drop any stored preferences for a table + user (reset to defaults).
     */
    public function forget(string $tableKey, ?Authenticatable $user): void;
}
