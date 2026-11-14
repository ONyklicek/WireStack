<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Data\EloquentDataSource;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

/**
 * How many records each option of a select filter would leave — the counts
 * beside "Admin (3)", and the option list itself for a filter that takes its
 * options from the data ({@see SelectFilter::optionsFromColumn()}).
 *
 * A count answers "what would I get if I picked this", so it is taken over the
 * table as every OTHER filter and the search narrow it, and never by the filter
 * being counted: counting Role under "Role is Admin" would say every other role
 * has nothing, and the list would talk the user out of changing their mind.
 * That is the rule spreadsheets and faceted search settled on.
 *
 * One grouped query per counting filter, built by the same {@see TableQueryService}
 * as the rows, so a count can never disagree with what picking it shows.
 *
 * **Kept, not re-counted on every request.** A GROUP BY over the whole filtered
 * table is the most expensive thing a render does, and almost no request changes
 * its answer: ticking a row, opening a modal, saving a cell elsewhere. The
 * counts are cached under the grouped query itself — its SQL carries every
 * filter and the search, so another view is another entry — and the table's
 * {@see WriteGeneration}, so a write through the table retires them at once.
 * The TTL bounds only what the table cannot see: a write made somewhere else.
 *
 * **Never shared between viewers.** The key is owned by one component instance
 * (`$cacheOwner`, the Livewire id) on one database. Equal SQL is not equal data:
 * a tenant on its own database or schema, a scope applied outside the query, a
 * `generateQueryCacheKey()` override — two users with the same grouped query can
 * be looking at different rows, and an option list read from the data
 * ({@see SelectFilter::optionsFromColumn()}) would hand one of them the other's
 * values. What is saved is one user's repeated requests, which is the cost.
 *
 * Only an
 * Eloquent source over a plain column is counted; a relation path or a custom
 * data source has no single column to group by and is left uncounted.
 *
 * The host does not ask for a header filter whose column hides its cell on some
 * records ({@see Column::visibleForRecord()}):
 * a value list would print the values those cells keep out of sight.
 */
final class FilterFacets
{
    /**
     * Counts per option value, most common first.
     *
     * @param  array<string, mixed>  $filterValues  the panel filter state
     * @param  array<string, mixed>  $columnFilterValues  the header filter state
     * @param  string  $stateKey  where this filter's own state lives, to leave it out
     * @param  string|null  $cacheScope  the table's write scope ({@see WriteGeneration});
     *                                   null counts afresh
     * @param  string|null  $cacheOwner  who the cached counts belong to — one
     *                                   component instance; null counts afresh
     * @return array<string, int>|null null when the filter cannot be counted
     */
    public function count(
        Table $table,
        SelectFilter $filter,
        array $filterValues,
        array $columnFilterValues,
        ?string $search,
        string $stateKey,
        bool $isColumnFilter,
        ?string $cacheScope = null,
        int $ttl = 0,
        ?string $cacheOwner = null,
    ): ?array {
        if (! $table->getDataSource() instanceof EloquentDataSource
            || $filter->getRelation() !== null
            || str_contains($filter->getColumn(), '.')) {
            return null;
        }

        if ($isColumnFilter) {
            unset($columnFilterValues[$stateKey]);
        } else {
            Arr::forget($filterValues, $stateKey);
        }

        // A fresh service: the component's own one memoises the last plan, and
        // this query must not become the plan the rows are drawn from.
        $query = app(TableQueryService::class)->buildQuery(
            baseQuery: $table->getQuery(),
            table: $table,
            search: $search,
            filterValues: $filterValues,
            columnFilterValues: $columnFilterValues,
        );

        $column = $query->qualifyColumn($filter->getColumn());

        $grouped = $query->toBase()
            ->cloneWithout(['columns', 'orders', 'limit', 'offset', 'unionOrders', 'unionLimit', 'unionOffset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->select([$column.' as wire_facet', DB::raw('count(*) as wire_facet_count')])
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('wire_facet_count')
            ->limit($filter->getFacetLimit());

        if ($cacheScope === null || $cacheOwner === null || $ttl <= 0) {
            return $this->tally($grouped->get());
        }

        $connection = DB::connection($query->getModel()->getConnectionName());
        $key = 'wire_table_facets:'.$cacheOwner.':'
            .md5($connection->getName().'|'.$connection->getDatabaseName().'|'.$grouped->toSql().serialize($grouped->getBindings()))
            .':g'.app(WriteGeneration::class)->current($cacheScope);

        return Cache::remember($key, $ttl, fn (): array => $this->tally($grouped->get()));
    }

    /**
     * @param  iterable<\stdClass>  $rows
     * @return array<string, int>
     */
    private function tally(iterable $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $value = $row->wire_facet instanceof \BackedEnum ? $row->wire_facet->value : $row->wire_facet;
            $counts[(string) $value] = (int) $row->wire_facet_count;
        }

        return $counts;
    }
}
