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
     */
    public function build(string $namespace, array $state): string
    {
        return $namespace.':'.md5(serialize($this->normalize($state)));
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
