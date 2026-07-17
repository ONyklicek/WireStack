<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Services\SubRowFilters;

/**
 * Expanding a row to show its children.
 *
 * A host concern: these are the endpoints the expand chevrons, the sub-row
 * filter bar and "show all" call, so they stay on the Livewire component. The
 * filter *rules* are {@see SubRowFilters}'.
 *
 * The subtlety worth reading before touching getSubRows(): children are
 * eager-loaded for the whole page in one query to avoid N+1, but that fast path
 * is only safe when no sub-row filter is active and the loaded set is complete.
 * A limited eager load (subRowsLimit) ships a *_count alongside so a later
 * "show all" can tell it is looking at a partial set and fall back to querying
 * that parent. Get this wrong and the table silently shows the wrong children.
 */
trait CanExpandSubRows
{
    /**
     * Toggle expansion of a parent row to show/hide its sub-rows.
     */
    public function toggleRowExpansion(mixed $recordKey): void
    {
        $key = (string) $recordKey;
        $expanded = $this->tableState->get('rows.expanded', []);

        if (in_array($key, $expanded, true)) {
            $expanded = array_values(array_diff($expanded, [$key]));
        } else {
            $expanded[] = $key;
        }

        $this->tableState->set('rows.expanded', $expanded);
    }

    /**
     * Expand all rows to show sub-rows.
     */
    public function expandAllRows(): void
    {
        $table = $this->getTable();
        if (! $table->hasSubRows()) {
            return;
        }

        if ($table->isSubRowsDefaultExpanded()) {
            // Default expanded: clear the "collapsed" list
            $this->tableState->set('rows.expanded', []);
        } else {
            $records = $this->getTableRecords();
            $this->tableState->set('rows.expanded', $records->pluck($table->getPrimaryKey())
                ->map(fn ($k) => (string) $k)
                ->all());
        }
    }

    /**
     * Collapse all expanded rows.
     */
    public function collapseAllRows(): void
    {
        $table = $this->getTable();

        if ($table->hasSubRows() && $table->isSubRowsDefaultExpanded()) {
            // Default expanded: add all to "collapsed" list
            $records = $this->getTableRecords();
            $this->tableState->set('rows.expanded', $records->pluck($table->getPrimaryKey())
                ->map(fn ($k) => (string) $k)
                ->all());
        } else {
            $this->tableState->set('rows.expanded', []);
        }
    }

    /**
     * Check if a row is expanded.
     */
    public function isRowExpanded(mixed $recordKey): bool
    {
        $expanded = $this->tableState->get('rows.expanded', []);
        $isInList = in_array((string) $recordKey, $expanded, true);

        // When default expanded, the expandedRows list tracks *collapsed* rows
        if ($this->getTable()->isSubRowsDefaultExpanded()) {
            return ! $isInList;
        }

        return $isInList;
    }

    /**
     * Toggle flatten mode (show all sub-rows as regular rows).
     */
    public function toggleFlattenMode(): void
    {
        $this->tableState->set('rows.flattenMode', ! $this->tableState->get('rows.flattenMode'));
    }

    /**
     * Get sub-rows for a parent record.
     * Applies sub-row filters if enabled.
     * When no relation is set, returns the record itself as a single-item collection.
     */
    public function getSubRows(mixed $record): Collection
    {
        $table = $this->getTable();
        if (! $table->hasSubRows()) {
            return collect();
        }

        // No relation — detail row mode: show the record itself
        if ($table->getSubRowRelation() === null) {
            return collect([$record]);
        }

        // Resolve active sort and "show all" flag for this specific parent.
        $relation = $table->getSubRowRelation();
        $sort = $this->getSubRowSort();
        $parentKey = $record->getKey();
        $showAll = (bool) ($this->tableState->get('rows.subRowsShowAll', [])[$parentKey] ?? false);

        // Fast path: sub-rows were eager-loaded for the whole page in one query
        // (see eagerLoadSubRows). Read from memory instead of querying per parent.
        //
        // Only safe when no sub-row filters are active. The relation may also be
        // eager-loaded by the caller's base query (e.g. Invoice::with('items')),
        // in which case the loaded set is unfiltered — fall through to the query
        // path so active rows.subRowFilters are honoured.
        if ($record->relationLoaded($relation) && ! $this->hasActiveSubRowFilters()) {
            $items = $record->getRelation($relation);

            // A limited eager load (subRowsLimit) ships a loadCount alongside.
            // If show-all was enabled after loading, memory holds only `limit`
            // rows — fall through to the query for this parent's full set.
            $loadedCount = $record->getAttribute(Str::snake($relation).'_count');
            $isPartialLoad = $loadedCount !== null && $items->count() < (int) $loadedCount;

            if (! ($showAll && $isPartialLoad)) {
                if (! $showAll && $table->getSubRowsLimit()) {
                    $items = $items->take($table->getSubRowsLimit());
                }

                return $items->values();
            }
        }

        $query = $table->getSubRowsQuery($record, $sort, applyLimit: ! $showAll);

        // Main-table filters scoped to sub-rows (Filter::subRows()) constrain
        // the displayed children the same way they constrained the parents.
        $query = $this->applySubRowScopedFilters($query);

        // Apply sub-row filters
        $query = $this->applyInteractiveSubRowFilters($query);

        return $query->get();
    }

    /**
     * Eager-load sub-rows for the records that will actually render them
     * (expanded rows, or every row in flatten mode), in a single query —
     * replacing the per-parent N+1 queries.
     *
     * Skipped when sub-row filters are active, since per-parent filtering with
     * custom filter callbacks can't be expressed safely inside one eager-load
     * closure; those fall back to the per-parent query path in getSubRows().
     *
     * @param  LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>|Collection<int, Model>  $records
     */
    protected function eagerLoadSubRows(LengthAwarePaginator|Paginator|CursorPaginator|Collection $records): void
    {
        $table = $this->getTable();

        if (! $table->hasSubRows() || $table->getSubRowRelation() === null) {
            return;
        }

        // Don't eager-load when sub-row filters are active (correctness over speed).
        if ($this->hasActiveSubRowFilters()) {
            return;
        }

        $collection = $records instanceof Collection ? $records : $records->getCollection();
        if ($collection->isEmpty()) {
            return;
        }

        // Only load sub-rows that will be displayed.
        $flatten = (bool) $this->tableState->get('rows.flattenMode');
        $target = $flatten
            ? $collection
            : $collection->filter(fn ($record) => $this->isRowExpanded($record->getKey()));

        if ($target->isEmpty()) {
            return;
        }

        $relation = $table->getSubRowRelation();
        $sort = $this->getSubRowSort();
        $callback = $table->getSubRowQueryCallback();
        $limit = $table->getSubRowsLimit();

        $constrain = function ($query) use ($table, $sort, $callback) {
            if ($callback) {
                $query = $callback($query) ?? $query;
            }

            // Sub-row scoped main filters are global (same constraint for every
            // parent), so unlike interactive per-parent filters they are safe
            // to express inside the single eager-load closure.
            $this->applySubRowScopedFilters($query);

            $sortColumn = $sort['column'] ?? $table->getSubRowsDefaultSort();
            $sortDirection = $sort['direction'] ?? $table->getSubRowsDefaultSortDirection();

            if ($sortColumn !== null && $table->isSubRowColumnSortable($sortColumn)) {
                $query->orderBy($sortColumn, $sortDirection === 'desc' ? 'desc' : 'asc');
            }
        };

        // No display limit, or the framework can't limit an eager load per
        // parent (Laravel < 11): load the full sets in one query. getSubRows()
        // applies the display limit in memory and counts the loaded relation,
        // so behaviour stays correct — only the memory win is lost.
        if (! $limit || ! $this->supportsPerParentEagerLimit()) {
            $target->load([$relation => $constrain]);

            return;
        }

        // With a limit, loading full child sets just to count them wastes
        // memory on large relations. Parents flagged "show all" still need the
        // full set; the rest load only `limit` rows per parent (native
        // eager-load limit — window function) plus an exact count for the
        // "show more" affordance (read via getSubRowsTotalCount()).
        $showAll = $this->tableState->get('rows.subRowsShowAll', []);

        [$fullTargets, $limitedTargets] = $target->partition(
            fn ($record) => (bool) ($showAll[$record->getKey()] ?? false),
        );

        if ($fullTargets->isNotEmpty()) {
            $fullTargets->load([$relation => $constrain]);
        }

        if ($limitedTargets->isNotEmpty()) {
            $limitedTargets->load([$relation => function ($query) use ($constrain, $limit) {
                $constrain($query);
                $query->limit($limit);
            }]);

            // Counts ignore ordering — apply only the row constraints.
            $limitedTargets->loadCount([$relation => function ($query) use ($callback) {
                if ($callback) {
                    $query = $callback($query) ?? $query;
                }

                $this->applySubRowScopedFilters($query);
            }]);
        }
    }

    /**
     * Whether the framework can limit an eager load per parent.
     *
     * Per-parent eager-load limits (a window function under the hood) arrived
     * in Laravel 11 via Query\Builder::groupLimit(). On Laravel 10 calling
     * ->limit() inside an eager-load closure applies a single global LIMIT
     * across all parents, so the limited fast path must be skipped there.
     */
    protected function supportsPerParentEagerLimit(): bool
    {
        return method_exists(\Illuminate\Database\Query\Builder::class, 'groupLimit');
    }

    /**
     * Reset sub-row filters.
     */
    public function resetSubRowFilters(): void
    {
        $this->tableState->set('rows.subRowFilters', []);
    }

    /**
     * Main-table filters scoped to the sub-row relation (Filter::subRows())
     * paired with their active values.
     *
     * @return array<int, array{0: Filter, 1: mixed}>
     */
    protected function getActiveSubRowScopedFilters(): array
    {
        return app(SubRowFilters::class)->activeScoped($this->getTable(), $this->tableState->get('filters', []));
    }

    /**
     * Constrain a child query by the active sub-row scoped main filters.
     *
     * @template TQuery of Builder<Model>|EloquentRelation<Model, Model, mixed>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    protected function applySubRowScopedFilters(Builder|EloquentRelation $query): Builder|EloquentRelation
    {
        return app(SubRowFilters::class)->applyScoped($query, $this->getTable(), $this->tableState->get('filters', []));
    }

    /**
     * Constrain a child query by the interactive sub-row filter bar values.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyInteractiveSubRowFilters(Builder $query): Builder
    {
        return app(SubRowFilters::class)->applyInteractive($query, $this->getTable(), $this->tableState->get('rows.subRowFilters', []));
    }

    /**
     * Whether at least one interactive sub-row filter is active. Disables the
     * eager-load / in-memory fast paths, which would bypass per-parent filtering.
     */
    protected function hasActiveSubRowFilters(): bool
    {
        return app(SubRowFilters::class)->hasActiveInteractive($this->getTable(), $this->tableState->get('rows.subRowFilters', []));
    }

    /**
     * Livewire hook for sub-row filter updates.
     */
    public function updatedSubRowFilters(): void
    {
        // Sub-row filters don't need pagination reset
    }

    /**
     * Current sub-row sort state, or null when none is active.
     *
     * @return array{column: string, direction: string}|null
     */
    public function getSubRowSort(): ?array
    {
        $sort = $this->tableState->get('rows.subRowSort');

        if (! is_array($sort) || empty($sort['column'])) {
            return null;
        }

        return [
            'column' => (string) $sort['column'],
            'direction' => ($sort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }

    /**
     * Toggle sub-row sorting by a column. Clicking the active column flips the
     * direction; clicking a new column sorts it ascending.
     */
    public function sortSubRows(string $column): void
    {
        $table = $this->getTable();

        if (! $table->isSubRowColumnSortable($column)) {
            return;
        }

        $current = $this->getSubRowSort();

        if ($current !== null && $current['column'] === $column) {
            $direction = $current['direction'] === 'asc' ? 'desc' : 'asc';
        } else {
            $direction = 'asc';
        }

        $this->tableState->set('rows.subRowSort', ['column' => $column, 'direction' => $direction]);
    }

    /**
     * Reveal all sub-rows for a parent, bypassing the configured subRowsLimit.
     */
    public function showAllSubRows(string|int $parentKey): void
    {
        $showAll = $this->tableState->get('rows.subRowsShowAll', []);
        $showAll[$parentKey] = true;
        $this->tableState->set('rows.subRowsShowAll', $showAll);
    }

    /**
     * Whether a parent currently has its sub-rows fully expanded (show-all).
     */
    public function isSubRowsShowAll(string|int $parentKey): bool
    {
        return (bool) ($this->tableState->get('rows.subRowsShowAll', [])[$parentKey] ?? false);
    }

    /**
     * Total (unlimited) count of a parent's sub-rows, honouring sub-row filters.
     * Used to decide whether a "show more" affordance is needed.
     */
    public function getSubRowsTotalCount(mixed $record): int
    {
        $table = $this->getTable();

        if (! $table->hasSubRows() || $table->getSubRowRelation() === null) {
            return 0;
        }

        // Use the eager-loaded relation when present — no extra count query.
        // Skipped when sub-row filters are active: a caller-eager-loaded relation
        // (e.g. Invoice::with('items')) is unfiltered, so counting it would ignore
        // rows.subRowFilters and over-count the "show more" affordance.
        $relation = $table->getSubRowRelation();
        if ($record->relationLoaded($relation) && ! $this->hasActiveSubRowFilters()) {
            // Limited eager loads (subRowsLimit) ship an exact loadCount
            // alongside — prefer it; the loaded relation itself holds only
            // `limit` rows, so counting it would always cap at the limit.
            $loadedCount = $record->getAttribute(Str::snake($relation).'_count');

            if ($loadedCount !== null) {
                return (int) $loadedCount;
            }

            return $record->getRelation($relation)->count();
        }

        $query = $table->getSubRowsQuery($record, $this->getSubRowSort(), applyLimit: false);

        // Honour the same constraints used when listing.
        $query = $this->applySubRowScopedFilters($query);
        $query = $this->applyInteractiveSubRowFilters($query);

        return $query->count();
    }
}
