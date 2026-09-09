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
 * `['columns' => ['hidden' => string[]], 'rows' => ['expandAll' => ?bool]]`;
 * keep unknown keys intact so future preferences (sort, page size) can be added
 * without a contract change.
 *
 * ## Named views
 *
 * Every method takes a `$view` name, and `null` means the unnamed one: the
 * layout a user is looking at right now, which is what this contract stored
 * before names existed. A saved view is the same bag under a name, which is why
 * this grew a dimension instead of a second store — two owners of "this user's
 * state for this table" would drift, and the current layout would have had to be
 * mirrored into both.
 *
 * A driver keys on the triple (table, user, view). A shared view — one a whole
 * team can see — is a row with no user, so sharing needs no second mechanism
 * either.
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
     * @param  string|null  $view  Saved view name; null is the current layout
     * @return array<string, mixed>
     */
    public function load(string $tableKey, ?Authenticatable $user, ?string $view = null): array;

    /**
     * Persist the preferences for a table + user (create or replace).
     *
     * @param  array<string, mixed>  $preferences
     * @param  string|null  $view  Saved view name; null is the current layout
     */
    public function save(string $tableKey, ?Authenticatable $user, array $preferences, ?string $view = null): void;

    /**
     * Drop any stored preferences for a table + user (reset to defaults).
     *
     * @param  string|null  $view  Saved view name; null is the current layout
     */
    public function forget(string $tableKey, ?Authenticatable $user, ?string $view = null): void;

    /**
     * The names of this user's saved views for a table, in no particular order.
     *
     * The unnamed current layout is never in the list — it has no name to show
     * in a switcher, and offering it as one would let a user "restore" the state
     * they are already in.
     *
     * @return array<int, string>
     */
    public function views(string $tableKey, ?Authenticatable $user): array;
}
