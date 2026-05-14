<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Query\JoinRegistry;
use NyonCode\WireCore\Core\Query\QueryExecutor;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\QueryPlanner;
use NyonCode\WireCore\Core\Query\SortDefinition;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Table;

/**
 * Bridges Table configuration and Livewire state to the Core Query infrastructure.
 *
 * Converts columns, filters, search, and sorting into QueryPlanner inputs,
 * produces a QueryPlan, and executes it via QueryExecutor.
 *
 * Replaces the inline query building, accessor reflection, and metadata
 * analysis that previously lived in WithTable (~500 lines).
 */
final class TableQueryService
{
    private ?QueryPlan $lastPlan = null;

    private ?MetadataRegistry $registry = null;

    /**
     * Build the query for a table using the Core query infrastructure.
     *
     * Handles the full pipeline:
     * 1. Register model metadata
     * 2. Convert Table columns/filters/sorts → QueryPlanner inputs
     * 3. Plan the query (QueryPlanner → QueryPlan)
     * 4. Execute the plan (QueryExecutor → modified Builder)
     * 5. Apply custom callbacks that bypass the planner
     *
     * @param  Builder<Model>  $baseQuery  The base Eloquent query
     * @param  Table  $table  Table configuration
     * @param  string|null  $search  Current search term
     * @param  array<string, mixed>  $filterValues  Current filter values (keyed by filter name)
     * @param  string|null  $sortColumn  Current sort column name
     * @param  string  $sortDirection  Current sort direction
     * @param  array<string, mixed>  $columnFilterValues  Current column filter values
     * @return Builder<Model> Modified builder ready for ->get() or ->paginate()
     */
    public function buildQuery(
        Builder $baseQuery,
        Table $table,
        ?string $search = null,
        array $filterValues = [],
        ?string $sortColumn = null,
        string $sortDirection = 'asc',
        array $columnFilterValues = [],
    ): Builder {
        $modelClass = get_class($baseQuery->getModel());
        $this->registry = $this->buildMetadataRegistry($baseQuery, $modelClass, $table);

        $columns = $table->getColumns();
        $filters = $table->getFilters();

        // ── 1. Collect custom callbacks that bypass the planner ──
        $customSearchCallbacks = [];
        $customSortCallback = null;
        $customFilterCallbacks = [];

        foreach ($columns as $column) {
            if ($column->isSearchable() && $column->getSearchCallback() !== null) {
                $customSearchCallbacks[] = $column->getSearchCallback();
            }
        }

        // Check if the active sort column has a custom callback
        if ($sortColumn !== null) {
            $sortColumnObj = $this->findColumn($columns, $sortColumn);
            if ($sortColumnObj?->getSortCallback() !== null) {
                $customSortCallback = $sortColumnObj->getSortCallback();
            }
        }

        // Collect filter custom query callbacks
        foreach ($filters as $filter) {
            $value = $filterValues[$filter->getName()] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (! $filter->canView()) {
                continue;
            }
            if ($filter->getQueryCallback() !== null) {
                $customFilterCallbacks[] = ['callback' => $filter->getQueryCallback(), 'value' => $value];
            }
        }

        // ── 2. Build QueryPlanner inputs (only for columns/filters WITHOUT custom callbacks) ──
        $plannerColumns = $this->buildPlannerColumns($columns);
        $plannerFilters = $this->buildPlannerFilters($filters, $filterValues, $columnFilterValues, $columns);
        $plannerSorts = $this->buildPlannerSorts($sortColumn, $sortDirection, $columns, $customSortCallback !== null);
        $searchTerm = ! empty($search) && ! empty($customSearchCallbacks) ? null : $search;

        // ── 3. Plan ──
        $joinRegistry = new JoinRegistry;
        $planner = new QueryPlanner($this->registry, $joinRegistry);
        $this->lastPlan = $planner->plan(
            modelClass: $modelClass,
            columns: $plannerColumns,
            filters: $plannerFilters,
            sorts: $plannerSorts,
            search: ! empty($search) ? $searchTerm : null,
        );

        // ── 4. Execute plan ──
        $executor = new QueryExecutor;
        $query = $executor->execute($baseQuery, $this->lastPlan, $searchTerm);

        // ── 5. Apply custom callbacks (these bypass the planner) ──

        // Custom search callbacks
        if (! empty($search) && ! empty($customSearchCallbacks)) {
            $query = $query->where(function (Builder $q) use ($customSearchCallbacks, $search) {
                foreach ($customSearchCallbacks as $callback) {
                    $q->orWhere(function (Builder $subQ) use ($callback, $search) {
                        call_user_func($callback, $subQ, $search);
                    });
                }
            });
        }

        // Custom sort callback
        if ($customSortCallback !== null) {
            $query = call_user_func($customSortCallback, $query, $sortDirection);
        }

        // Custom filter callbacks
        foreach ($customFilterCallbacks as $item) {
            $query = call_user_func($item['callback'], $query, $item['value']);
        }

        // Column-level filters with custom callbacks
        foreach ($columns as $column) {
            if (! $column->isFilterable()) {
                continue;
            }
            $value = $columnFilterValues[$column->getName()] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            if ($column->getFilterQueryCallback() !== null) {
                $query = call_user_func($column->getFilterQueryCallback(), $query, $value);
            }
        }

        return $query;
    }

    /**
     * Get the last QueryPlan built by buildQuery().
     * Useful for debugging and the debugQueryPlan() feature.
     */
    public function getLastPlan(): ?QueryPlan
    {
        return $this->lastPlan;
    }

    /**
     * Get the MetadataRegistry used by the last buildQuery() call.
     */
    public function getLastRegistry(): ?MetadataRegistry
    {
        return $this->registry;
    }

    /**
     * Build a MetadataRegistry for the base model and its relations.
     *
     * @param  Builder<Model>  $baseQuery
     * @param  class-string<Model>  $modelClass
     */
    private function buildMetadataRegistry(Builder $baseQuery, string $modelClass, Table $table): MetadataRegistry
    {
        $registry = new MetadataRegistry;

        // Register the base model — auto-extracts table name, primary key, casts, fillable, relations
        $model = $baseQuery->getModel();
        $relations = $this->discoverRelationsFromColumns($table);
        $registry->registerModel($modelClass, $relations);

        // Register related models that columns reference
        foreach ($relations as $relationName) {
            $this->registerRelationChain($registry, $model, $relationName);
        }

        return $registry;
    }

    /**
     * Discover relation names from table columns and filters.
     *
     * @return array<int, string>
     */
    private function discoverRelationsFromColumns(Table $table): array
    {
        $relations = [];

        foreach ($table->getColumns() as $column) {
            $relation = $column->getRelation();
            if ($relation !== null && ! in_array($relation, $relations, true)) {
                $relations[] = $relation;
            }
        }

        foreach ($table->getFilters() as $filter) {
            if (method_exists($filter, 'getRelation') && $filter->getRelation()) {
                $relation = $filter->getRelation();
                if (! in_array($relation, $relations, true)) {
                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    /**
     * Register a relation chain (e.g., "user.company") into the registry.
     */
    private function registerRelationChain(MetadataRegistry $registry, Model $model, string $relationPath): void
    {
        $parts = explode('.', $relationPath);
        $current = $model;

        foreach ($parts as $part) {
            $methodName = Str::camel($part);
            if (! method_exists($current, $methodName)) {
                return;
            }

            try {
                $relationInstance = $current->{$methodName}();
                $relatedModel = $relationInstance->getRelated();
                $relatedClass = get_class($relatedModel);

                if (! $registry->hasModel($relatedClass)) {
                    $registry->registerModel($relatedClass);
                }

                $current = $relatedModel;
            } catch (\Throwable) {
                return;
            }
        }
    }

    /**
     * Convert Column objects to DataComponent[] for the planner.
     * Only includes columns that participate in query planning (searchable/sortable).
     *
     * @param  array<int, Column>  $columns
     * @return array<int, Column>
     */
    private function buildPlannerColumns(array $columns): array
    {
        // The planner needs all columns to plan eager loads and joins
        return $columns;
    }

    /**
     * Convert active filter values + Filter config → FilterDefinition[] for the planner.
     *
     * @param  array<int, Filter>  $filters
     * @param  array<string, mixed>  $filterValues
     * @param  array<string, mixed>  $columnFilterValues
     * @param  array<int, Column>  $columns
     * @return array<int, FilterDefinition>
     */
    private function buildPlannerFilters(
        array $filters,
        array $filterValues,
        array $columnFilterValues,
        array $columns,
    ): array {
        $definitions = [];

        // Global filters (without custom query callbacks — those are handled post-plan)
        foreach ($filters as $filter) {
            $value = $filterValues[$filter->getName()] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            if (! $filter->canView()) {
                continue;
            }
            // Skip filters with custom query callbacks — they bypass the planner
            if ($filter->getQueryCallback() !== null) {
                continue;
            }

            $operator = ($filter->isMultiple() && is_array($value)) ? 'in' : '=';
            $filterColumn = $filter->getColumn();

            $definitions[] = FilterDefinition::make(
                column: $filterColumn,
                operator: $operator,
                value: $value,
            );
        }

        // Column-level filters (without custom callbacks)
        foreach ($columns as $column) {
            if (! $column->isFilterable()) {
                continue;
            }
            $value = $columnFilterValues[$column->getName()] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            // Skip columns with custom filter callbacks
            if ($column->getFilterQueryCallback() !== null) {
                continue;
            }

            $definitions[] = FilterDefinition::make(
                column: $column->getName(),
                operator: '=',
                value: $value,
            );
        }

        return $definitions;
    }

    /**
     * Convert sort state → SortDefinition[] for the planner.
     *
     * @param  array<int, Column>  $columns
     * @return array<int, SortDefinition>
     */
    private function buildPlannerSorts(
        ?string $sortColumn,
        string $sortDirection,
        array $columns,
        bool $hasCustomCallback,
    ): array {
        if ($sortColumn === null || $hasCustomCallback) {
            return [];
        }

        $columnObj = $this->findColumn($columns, $sortColumn);
        if ($columnObj === null || ! $columnObj->isSortable()) {
            return [];
        }

        return [SortDefinition::make(
            column: $columnObj->getName(),
            direction: $sortDirection,
        )];
    }

    /**
     * Find a column by name.
     *
     * @param  array<int, Column>  $columns
     */
    private function findColumn(array $columns, string $name): ?Column
    {
        foreach ($columns as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        return null;
    }
}
