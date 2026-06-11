<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Capabilities\CapabilityResolver;
use NyonCode\WireCore\Core\Metadata\AccessorMetadata;
use NyonCode\WireCore\Core\Metadata\ColumnMetadata;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Plugin\Hooks\TableConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableQueriedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableQueryingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Query\JoinRegistry;
use NyonCode\WireCore\Core\Query\QueryExecutor;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\QueryPlanner;
use NyonCode\WireCore\Core\Query\SortDefinition;
use NyonCode\WireCore\Core\Relations\RelationPath;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
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

    private ?string $currentModelClass = null;

    /** @var array<class-string<Model>, true> */
    private array $lazilyRegistered = [];

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
        $this->currentModelClass = $modelClass;
        $this->lazilyRegistered = [];
        $this->registry = $this->buildMetadataRegistry($baseQuery, $modelClass, $table);
        $pluginManager = $this->resolvePluginManager();

        $columns = $table->getColumns();
        $filters = $table->getFilters();

        // ── 0. Plugin hook: table.configuring ──
        if ($pluginManager !== null) {
            $payload = $pluginManager->runHook('table.configuring', [
                'table' => $table,
                'columns' => $columns,
                'filters' => $filters,
            ]);
            $columns = $payload['columns'] ?? $columns;
            $filters = $payload['filters'] ?? $filters;

            // Typed hook (parallel API — both array and typed hooks run)
            $typedPayload = $pluginManager->runTypedHook(
                'table.configuring',
                new TableConfiguringPayload($table, $columns, $filters),
            );
            $columns = $typedPayload->columns;
            $filters = $typedPayload->filters;
        }

        // ── 1. Collect custom callbacks that bypass the planner ──
        // WARNING: custom callbacks receive the raw query builder. Using orWhere()
        // inside a callback can escape the table's base authorization scope.
        // Always use $query->where(fn($q) => $q->...) to scope conditions safely.
        $customSearchCallbacks = [];
        $customSortCallback = null;
        $customFilterCallbacks = [];
        $subRowScopedFilters = [];
        $subRowRelation = $table->getSubRowRelation();

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
            $raw = $filterValues[$filter->getName()] ?? null;
            if ($raw === null || $raw === '' || $raw === []) {
                continue;
            }
            if (! $filter->canView()) {
                continue;
            }

            $value = $filter->extractValue($raw);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if ($filter->appliesToSubRows() && $subRowRelation !== null) {
                // Sub-row scoped filter — constrains children, applied below as
                // one combined whereHas + as a constraint on rollup aggregates.
                $subRowScopedFilters[] = ['filter' => $filter, 'value' => $value];
            } elseif ($filter->getQueryCallback() !== null) {
                $customFilterCallbacks[] = ['callback' => $filter->getQueryCallback(), 'value' => $value];
            } elseif ($filter->bypassesPlanner() || (is_array($value) && ! $filter->isMultiple())) {
                // Multi-field filter (e.g. NumberRange, DateFilter range) or a
                // filter whose constraint the planner can't express — route through apply()
                $customFilterCallbacks[] = [
                    'callback' => fn (Builder $q, mixed $v) => $filter->apply($q, $v),
                    'value' => $value,
                ];
            }
        }

        // Sub-row scoped filters: a parent survives only when at least one child
        // matches ALL active sub-row filters combined — one whereHas, not one per
        // filter, so the surviving parents actually have displayable children.
        $subRowConstraint = null;
        if ($subRowScopedFilters !== []) {
            $subRowConstraint = static function (Builder $q) use ($subRowScopedFilters): void {
                foreach ($subRowScopedFilters as $item) {
                    $item['filter']->apply($q, $item['value']);
                }
            };

            $customFilterCallbacks[] = [
                'callback' => fn (Builder $q) => $q->whereHas($subRowRelation, $subRowConstraint),
                'value' => null,
            ];
        }

        // ── 2. Build QueryPlanner inputs (only for columns/filters WITHOUT custom callbacks) ──
        $plannerColumns = $this->buildPlannerColumns($columns);
        $plannerFilters = $this->buildPlannerFilters($filters, $filterValues, $columnFilterValues, $columns, $subRowRelation !== null);
        $plannerSorts = $this->buildPlannerSorts($sortColumn, $sortDirection, $columns, $customSortCallback !== null);
        $searchTerm = ! empty($search) && ! empty($customSearchCallbacks) ? null : $search;

        // ── 2.5 Plugin hook: table.querying (pre-plan, can force sort override) ──
        if ($pluginManager !== null) {
            $queryingPayload = $pluginManager->runHook('table.querying', [
                'table' => $table,
                'columns' => $columns,
                'filters' => $filters,
                'sort_column' => $sortColumn,
                'sort_direction' => $sortDirection,
                'search' => $search,
            ]);

            // Plugins can force sort override (e.g. SortablePlugin in reorder mode)
            if (isset($queryingPayload['force_sort_column'])) {
                $forceDirection = $queryingPayload['force_sort_direction'] ?? 'asc';
                $plannerSorts = [SortDefinition::make(
                    column: $queryingPayload['force_sort_column'],
                    direction: in_array($forceDirection, ['asc', 'desc'], true) ? $forceDirection : 'asc',
                )];
                $customSortCallback = null;
            }
        }

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

        // ── 3.5 Typed plugin hook: table.querying (post-plan, pre-execute) ──
        // Plugins that only need to observe the finished plan (e.g. for logging or
        // read-only inspection) may use this typed hook.
        //
        // NOTE: Do NOT use forceSortColumn here. Sort overrides must go through the
        // array-based table.querying hook (step 2.5 above) so they are applied
        // BEFORE the first plan() call. Setting forceSortColumn in the typed hook
        // would require re-running the full planner a second time.
        if ($pluginManager !== null) {
            $pluginManager->runTypedHook(
                'table.querying',
                new TableQueryingPayload($table, $this->lastPlan, $baseQuery),
            );
        }

        // ── 4. Execute plan (with plugin pipes appended) ──
        $executor = new QueryExecutor;

        if ($pluginManager !== null) {
            $pluginPipes = $pluginManager->getQueryPipes();
            if ($pluginPipes !== []) {
                $executor = $executor->withPipes([
                    ...$this->getDefaultExecutorPipes($executor, $baseQuery, $searchTerm),
                    ...array_values($pluginPipes),
                ]);
            }
        }

        $query = $executor->execute($baseQuery, $this->lastPlan, $searchTerm);

        // ── 4.5 Apply aggregate subqueries (withCount, withSum, etc.) ──
        // Rollups over the sub-row relation honour active sub-row scoped filters,
        // so rollup cells and footer grand totals reflect the filtered children.
        $query = $this->applyAggregates($query, $columns, $subRowRelation, $subRowConstraint);

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

        // ── 6. Plugin hook: table.queried (post-execution observation) ──
        if ($pluginManager !== null) {
            $pluginManager->runHook('table.queried', [
                'table' => $table,
                'query' => $query,
                'plan' => $this->lastPlan,
            ]);

            $pluginManager->runTypedHook(
                'table.queried',
                new TableQueriedPayload($table, $query, $this->lastPlan),
            );
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
     * Resolve the PluginManager if it is registered in the container.
     */
    private function resolvePluginManager(): ?PluginManager
    {
        if (! app()->bound(PluginManager::class)) {
            return null;
        }

        return app(PluginManager::class);
    }

    /**
     * Get default executor pipes to merge with plugin pipes.
     *
     * @param  Builder<Model>  $builder
     * @return array<int, QueryPipe>
     */
    private function getDefaultExecutorPipes(QueryExecutor $executor, Builder $builder, ?string $searchTerm): array
    {
        return $executor->getDefaultPipes($builder, $searchTerm);
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
            if ($filter->getRelation()) {
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
     *
     * Auto-resolves capabilities from MetadataRegistry so columns backed by
     * a real DB column inherit Searchable/Sortable/Filterable without the user
     * needing to call ->searchable()->sortable() explicitly.
     *
     * Supports dot-notation relation chains (e.g., "company.name") by walking
     * the registry to the terminal model and reading its column/accessor metadata.
     *
     * @param  array<int, Column>  $columns
     * @return array<int, Column>
     */
    private function buildPlannerColumns(array $columns): array
    {
        if ($this->registry === null || $this->currentModelClass === null) {
            return $columns;
        }

        $resolver = new CapabilityResolver;

        foreach ($columns as $column) {
            [$columnMeta, $accessorMeta] = $this->resolveColumnMeta($column->getName());

            if ($columnMeta !== null) {
                $resolved = $resolver->resolve($columnMeta, null, $column->getCapabilities()->all());
                $column->capabilities($resolved);
            } elseif ($accessorMeta !== null) {
                $resolved = $resolver->resolve(null, $accessorMeta, $column->getCapabilities()->all());
                $column->capabilities($resolved);
            }
        }

        return $columns;
    }

    /**
     * Resolve ColumnMetadata or AccessorMetadata for a given column name.
     *
     * Handles dot-notation by walking the relation chain through the registry,
     * lazily registering related models that were not part of the initial scan.
     *
     * Aggregate columns (e.g. "orders->count()") have no DB column to inspect,
     * so auto-detection returns [null, null] and buildPlannerColumns leaves their
     * capabilities unchanged. Explicit declarations (->sortable(), ->searchable())
     * set via the fluent API are preserved and honoured by the planner.
     *
     * @return array{0: ?ColumnMetadata, 1: ?AccessorMetadata}
     */
    private function resolveColumnMeta(string $name): array
    {
        $parsed = RelationPath::parse($name);

        if ($parsed->isSimple()) {
            return [
                $this->registry->getColumn($this->currentModelClass, $name),
                $this->registry->getAccessor($this->currentModelClass, $name),
            ];
        }

        if ($parsed->isAggregate()) {
            return [null, null];
        }

        $currentModel = $this->currentModelClass;

        foreach ($parsed->getRelationSegments() as $segment) {
            $relation = $this->registry->getRelation($currentModel, $segment->name);

            if ($relation === null || $relation->relatedModel === null) {
                return [null, null];
            }

            $currentModel = $relation->relatedModel;

            if (! $this->registry->hasModel($currentModel) && ! isset($this->lazilyRegistered[$currentModel])) {
                $this->registry->registerModel($currentModel);
                $this->lazilyRegistered[$currentModel] = true;
            }
        }

        $terminalColumn = $parsed->getColumnName();

        return [
            $this->registry->getColumn($currentModel, $terminalColumn),
            $this->registry->getAccessor($currentModel, $terminalColumn),
        ];
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
        bool $subRowsEnabled = false,
    ): array {
        $this->enrichSelectFiltersWithEnumOptions($filters);

        $definitions = [];

        // Global filters (without custom query callbacks — those are handled post-plan)
        foreach ($filters as $filter) {
            $raw = $filterValues[$filter->getName()] ?? null;
            if ($raw === null || $raw === '' || $raw === []) {
                continue;
            }
            if (! $filter->canView()) {
                continue;
            }
            // Skip filters with custom query callbacks — they bypass the planner
            if ($filter->getQueryCallback() !== null) {
                continue;
            }
            // Sub-row scoped and planner-incompatible filters route through apply()
            if ($filter->bypassesPlanner() || ($filter->appliesToSubRows() && $subRowsEnabled)) {
                continue;
            }

            $value = $filter->extractValue($raw);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // Multi-field filters route through apply() — skip in planner
            if (is_array($value) && ! $filter->isMultiple()) {
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
     * Auto-populate SelectFilter options from Eloquent enum casts when the
     * filter has no explicit options set. Users can override at any time by
     * calling ->options([...]) on the filter.
     *
     * @param  array<int, Filter>  $filters
     */
    private function enrichSelectFiltersWithEnumOptions(array $filters): void
    {
        if ($this->registry === null || $this->currentModelClass === null) {
            return;
        }

        if (! $this->registry->hasModel($this->currentModelClass)) {
            return;
        }

        $modelMeta = $this->registry->getModelMetadata($this->currentModelClass);

        foreach ($filters as $filter) {
            if (! ($filter instanceof SelectFilter) || $filter->getOptions() !== []) {
                continue;
            }

            $cast = $modelMeta->getCast($filter->getColumn());

            if ($cast === null || ! enum_exists($cast)) {
                continue;
            }

            $options = [];

            /** @var class-string<\UnitEnum> $enumClass */
            $enumClass = $cast;

            foreach ($enumClass::cases() as $case) {
                $key = $case instanceof \BackedEnum ? (string) $case->value : $case->name;
                $options[$key] = $case->name;
            }

            $filter->options($options);
        }
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
     * Apply withCount / withSum / withAvg / withMin / withMax for aggregate columns.
     *
     * When the aggregated relation is the table's sub-row relation and sub-row
     * scoped filters are active, the aggregate subquery is constrained the same
     * way the displayed children are (the constrained array syntax keeps the
     * default alias, e.g. items_sum_total).
     *
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @return Builder<Model>
     */
    private function applyAggregates(
        Builder $query,
        array $columns,
        ?string $subRowRelation = null,
        ?Closure $subRowConstraint = null,
    ): Builder {
        foreach ($columns as $column) {
            if (! $column->isAggregate()) {
                continue;
            }

            $relation = $column->getAggregateRelation();
            $function = $column->getAggregateFunction();
            $aggregateCol = $column->getAggregateColumn();

            $target = ($subRowConstraint !== null && $relation === $subRowRelation)
                ? [$relation => $subRowConstraint]
                : $relation;

            match ($function) {
                'count' => $query->withCount($target),
                'sum' => $query->withSum($target, $aggregateCol),
                'avg' => $query->withAvg($target, $aggregateCol),
                'min' => $query->withMin($target, $aggregateCol),
                'max' => $query->withMax($target, $aggregateCol),
                default => null,
            };
        }

        return $query;
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
