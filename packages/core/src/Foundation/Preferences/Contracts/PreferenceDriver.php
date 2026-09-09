<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Preferences\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;

/**
 * A pluggable store for "what this user has done to this surface".
 *
 * A driver persists a small, JSON-serializable bag keyed by a **surface key**
 * and the current user, so a layout survives a reload. Implement this to back
 * preferences with any store — the database, the session, a cache, an external
 * service — and select it through config or per surface.
 *
 * ## Why it is in Foundation and not in the package that first needed it
 *
 * It started as `WireTable\Preferences\TablePreferenceDriver`, because a table's
 * hidden-column set was the first thing anyone wanted remembered. Then a
 * dashboard needed the same thing — an ordered list of widgets, their sizes,
 * which are hidden — keyed by a dashboard and a user rather than a table and a
 * user. That is the same shape exactly, and `Widgets/` is in `wire-core`, which
 * `wire-table` depends on, so from a widget the table's store could not be
 * reached.
 *
 * Writing a second one would have been a second implementation of "a per-user
 * JSON bag keyed by a surface and a user", which the adapter rule forbids. So it
 * came down here instead, with its three drivers and its table, and `wire-table`
 * now asks Foundation the question it used to answer itself.
 *
 * ## The bag is open on purpose
 *
 * A table stores `['columns' => ['hidden' => string[]], 'rows' => [...]]`; a
 * dashboard stores its widget layout. Neither knows about the other, and a
 * driver must keep keys it does not recognise intact — that is what lets a
 * surface grow a new preference without a contract change, and what stops two
 * surfaces sharing a key from erasing each other.
 *
 * ## Named views
 *
 * Every method takes a `$view` name, and `null` means the unnamed one: the
 * layout the user is looking at right now. A saved view is the same bag under a
 * name, which is why this grew a dimension instead of a second store — two
 * owners of "this user's state for this surface" would drift, and the current
 * layout would have had to be mirrored into both.
 *
 * A driver keys on the triple (surface, user, view). A shared view — one a whole
 * team can see — is a row with no user, so sharing needs no second mechanism
 * either.
 *
 * @see PreferenceManager
 */
interface PreferenceDriver
{
    /**
     * Load the stored preferences for a surface + user.
     *
     * Return an empty array when nothing has been saved yet (the surface then
     * keeps its configured defaults); return the stored bag otherwise.
     *
     * @param  string  $surfaceKey  Stable identifier for the surface — a table key, a dashboard key
     * @param  Authenticatable|null  $user  The current user (null for a guest)
     * @param  string|null  $view  Saved view name; null is the current layout
     * @return array<string, mixed>
     */
    public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array;

    /**
     * Persist the preferences for a surface + user (create or replace).
     *
     * @param  array<string, mixed>  $preferences
     * @param  string|null  $view  Saved view name; null is the current layout
     */
    public function save(string $surfaceKey, ?Authenticatable $user, array $preferences, ?string $view = null): void;

    /**
     * Drop any stored preferences for a surface + user (reset to defaults).
     *
     * @param  string|null  $view  Saved view name; null is the current layout
     */
    public function forget(string $surfaceKey, ?Authenticatable $user, ?string $view = null): void;

    /**
     * The names of this user's saved views for a surface, in no particular order.
     *
     * The unnamed current layout is never in the list — it has no name to show
     * in a switcher, and offering it as one would let a user "restore" the state
     * they are already in.
     *
     * @return array<int, string>
     */
    public function views(string $surfaceKey, ?Authenticatable $user): array;
}
