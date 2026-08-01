<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Canonical owner of the `cacheQuery()` cache key.
 *
 * A cached table serves a *paginated slice*, not a query. The slice is shaped
 * by state that never reaches the SQL a key is derived from — pagination is
 * applied inside the cache callback, so `perPage` and the page number are
 * invisible to `toSql()` — and by state a caller-supplied key cannot know
 * about at all. Every one of those inputs has to be in the key, or a table
 * with `cacheQuery()` freezes: changing the page size, the sort, the search
 * term or a filter keeps serving the entry cached under the same key until the
 * TTL runs out.
 *
 * Hence the split. A *namespace* says which table this is — the SQL by
 * default, or whatever `cacheQuery($ttl, $key)` / an overridden
 * generateQueryCacheKey() supplies. The state fingerprint says which view of
 * it, and is appended to every namespace without exception.
 *
 * And a *generation*, which is what makes a cached table safe to write to. A
 * cache entry cannot be found and deleted after a write: the namespace is
 * derived from the SQL, so every filter combination, search term and sort a
 * user ever opened has its own entry, and the writer knows none of them. The
 * generation sidesteps that — the counter is carried in every key, so moving it
 * retires the lot at once. The old entries are not deleted, they simply stop
 * being addressed and expire on their own TTL.
 *
 * Without it `cacheQuery()` and inline editing were mutually exclusive in a way
 * nothing announced: the edit committed, the notification said so, and the table
 * kept serving the pre-write rows to everyone until the TTL ran out.
 *
 * The counter itself belongs to {@see WriteGeneration}, not here: poll change
 * detection reads the same number for a reason of its own, and a cache-key
 * builder owning a shared write log would be the wrong place to look for it.
 * This only builds keys.
 */
final class TableQueryCacheKey
{
    /**
     * Default namespace for a table: its query, minus the pagination that is
     * applied later inside the cache callback.
     *
     * @param  Builder<Model>  $query
     */
    public function namespaceFor(Builder $query): string
    {
        return 'wire_table:'.md5($query->toSql().serialize($query->getBindings()));
    }

    /**
     * Build the cache key for one view of one table.
     *
     * @param  array<string, mixed>  $state  Result-shaping state (see WithTable::queryCacheState()).
     * @param  int|null  $generation  The scope's write generation ({@see WriteGeneration}).
     *                                Null keeps the pre-generation key shape for a caller
     *                                with no data source to track.
     */
    public function build(string $namespace, array $state, ?int $generation = null): string
    {
        $key = $namespace.':'.md5(serialize($this->normalize($state)));

        return $generation === null ? $key : $key.':g'.$generation;
    }

    /**
     * Normalise the state bag so equivalent views share one entry.
     *
     * Keys are sorted (recursively) because a state array built in a different
     * order still describes the same view, and numbers are stringified because
     * `perPage` arrives from the wire:model select as a numeric string while
     * the mount default is an int. Booleans are left alone — is_numeric()
     * rejects them, so a `true` toggle never collapses onto a `1`.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function normalize(array $state): array
    {
        ksort($state);

        foreach ($state as $key => $value) {
            if (is_array($value)) {
                $state[$key] = $this->normalize($value);
            } elseif (is_numeric($value)) {
                $state[$key] = (string) $value;
            }
        }

        return $state;
    }
}
