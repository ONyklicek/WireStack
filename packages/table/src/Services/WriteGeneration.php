<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * A counter that moves whenever a table writes, shared across every process.
 *
 * Canonical owner of "the data behind this table has moved", for the two things
 * that need to know and could otherwise disagree:
 *
 *  - the query cache, whose keys carry the generation so a write retires every
 *    cached slice at once (they cannot be found and deleted — the namespace is
 *    derived from the SQL, so every filter and search term a user ever opened
 *    has its own entry and the writer knows none of them);
 *  - poll change detection, which is otherwise blind to a change that happens
 *    inside the same second as the last one it saw.
 *
 * That second one is why this is not merely a cache detail. The default
 * detector is COUNT(*) + MAX(updated_at), and Eloquent stores `updated_at` to
 * the second — so an edit landing in the same second as the previous tick's
 * checksum is indistinguishable from nothing at all, and a live table would
 * simply never show it. Not "show it late": never, until some later write moved
 * the timestamp into a new second. The counter makes any write through a table
 * visible immediately, whatever the clock says, while MAX(updated_at) still
 * catches writes that never went through one (a job, a migration, another app).
 *
 * Scoped by data source — the model class, normally — so two components listing
 * the same records tell each other, and a write to something else does not wake
 * either of them.
 */
final class WriteGeneration
{
    /**
     * The current generation of $scope. Missing means 0, so a table nothing has
     * written to yet behaves exactly as it would without this at all.
     *
     * An unreachable cache store means 0 too. This is read on the render path of
     * every cached table and on every poll tick, and it is an *optimisation* in
     * both: without it the query cache still expires on its TTL and change
     * detection still has COUNT + MAX(updated_at). A misconfigured or briefly
     * unavailable store must therefore degrade the feature, not take the page
     * down with it — a table that will not render because the cache is down is a
     * far worse failure than one that refreshes a second late.
     */
    public function current(string $scope): int
    {
        try {
            return (int) Cache::get($this->key($scope), 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Move $scope on by one.
     *
     * Read-then-write rather than Cache::increment(): increment is not offered
     * by every store for a key that does not exist yet, and losing the race is
     * harmless here — two writers landing on the same new number have both still
     * moved off the old one, which is the only property anything depends on.
     *
     * Stored forever on purpose. A generation that expired before the cached
     * slices it retired would resurrect them.
     *
     * Swallows a store failure for the same reason {@see current()} does: this
     * runs after a write that has already committed, and a cache that is down
     * must not turn a successful save into an error the user sees.
     */
    public function bump(string $scope): void
    {
        try {
            Cache::forever($this->key($scope), $this->current($scope) + 1);
        } catch (Throwable) {
            // The write landed; the other readers fall back to their own signals.
        }
    }

    private function key(string $scope): string
    {
        return 'wire_table:gen:'.md5($scope);
    }
}
