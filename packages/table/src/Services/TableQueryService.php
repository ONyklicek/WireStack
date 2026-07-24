<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;
use NyonCode\WireCore\Core\Plugin\Hooks\TableConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableQueriedPayload;
use NyonCode\WireCore\Core\Plugin\Hooks\TableQueryingPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Query\JoinRegistry;
use NyonCode\WireCore\Core\Query\QueryExecutor;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\QueryPlanner;
use NyonCode\WireCore\Core\Query\SortDefinition;
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
            // data_get so dotted (relation) filter names resolve their nested state.
            $raw = data_get($filterValues, $filter->getName());
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
            } elseif ($filter->getQueryCallback() !== null
                || $filter->bypassesPlanner()
                || (is_array($value) && ! $filter->isMultiple())) {
                // A custom ->query() callback, a multi-field filter (NumberRange,
                // DateFilter range) or a constraint the planner can't express.
                // All route through the filter's own apply(), the single owner of
                // value normalization and callback invocation — calling the raw
                // callback here instead skipped that normalization, so a ternary
                // callback saw the string 'false' (truthy) and both options
                // produced the same rows.
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
        $plannerColumns = $columns;
        $plannerFilters = [
            ...$this->buildPlannerFilters($filters, $filterValues, $subRowRelation !== null),
            ...$this->buildPlannerColumnFilters($columns, $columnFilterValues),
        ];
        $plannerSorts = $this->buildPlannerSorts($sortColumn, $sortDirection, $columns, $customSortCallback !== null);

        // Custom-search columns are excluded from the planner, but their callbacks
        // are OR-combined into the same search group as the default columns (see
        // ApplySearch) — so having one custom-search column no longer suppresses
        // every plain ->searchable() column. Only a non-empty term searches.
        $searchTerm = ! empty($search) ? $search : null;
        $searchCallbacks = ! empty($search) ? $customSearchCallbacks : [];

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
                    ...$executor->getDefaultPipes($baseQuery, $searchTerm, $searchCallbacks),
                    ...array_values($pluginPipes),
                ]);
            }
        }

        $query = $executor->execute($baseQuery, $this->lastPlan, $searchTerm, $searchCallbacks);

        // ── 4.4 Explicit eager-load hints (Column::loadRelations()) ──
        // For relations a display/url/color closure dereferences per row but which
        // have no column path, so the planner cannot discover them. Without this the
        // relation lazy-loads once per row (an N+1 the framework can't see inside a
        // closure); with() is additive and Laravel de-dupes against planned loads.
        /** @var array<int, string> $extraEagerLoads */
        $extraEagerLoads = [];
        foreach ($columns as $column) {
            foreach ($column->getEagerLoadRelations() as $relation) {
                $extraEagerLoads[] = $relation;
            }
        }
        if ($extraEagerLoads !== []) {
            // with() de-dupes internally against the planned eager loads.
            $query->with($extraEagerLoads);
        }

        // ── 4.5 Apply aggregate subqueries (withCount, withSum, etc.) ──
        // Rollups over the sub-row relation honour active sub-row scoped filters,
        // so rollup cells and footer grand totals reflect the filtered children.
        $query = app(AggregateSubqueries::class)->apply($query, $columns, $subRowRelation, $subRowConstraint);

        // ── 5. Apply custom callbacks (these bypass the planner) ──
        // Custom search callbacks are applied inside the executor's search group
        // (see ApplySearch) so they OR-combine with the default-column search.

        // Custom sort callback
        if ($customSortCallback !== null) {
            $query = call_user_func($customSortCallback, $query, $sortDirection);
        }

        // Custom filter callbacks
        foreach ($customFilterCallbacks as $item) {
            $query = call_user_func($item['callback'], $query, $item['value']);
        }

        // Column header filters that the planner cannot express as a single
        // column/operator/value clause (date whereDate, boolean "= false OR
        // IS NULL", or a custom ->filterUsing() callback) are applied here,
        // post-planner, through the canonical Column::applyFilter(). The rest
        // were folded into the QueryPlanner as FilterDefinitions above, so join
        // handling + qualification are shared with panel filters.
        foreach ($columns as $column) {
            if (! $column->isFilterable()) {
                continue;
            }
            $value = $columnFilterValues[$column->getName()] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            // Skip filters already expressed in the plan.
            if ($column->getFilter()?->toPlannerDefinitions($value) !== []) {
                continue;
            }

            $query = $column->applyFilter($query, $value);
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
     *
     * Registers the related models, and — for a belongs-to hop — the relation
     * metadata that lets QueryPlanner emit a LEFT JOIN so the column is sortable
     * and filterable in SQL (docs: "Simple belongsTo relations -> LEFT JOIN").
     * Only belongs-to is registered: it is the one relation whose join keys the
     * planner's join builder handles correctly and the only join the docs
     * promise. Every other relation stays join-less (getRelation() -> null ->
     * no join), so a hasOne-through can never be mis-joined as a plain hasOne.
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

                // Register the singular relations the join builder can key:
                // BelongsTo (FK on parent), HasOne (FK on related), and
                // HasOneThrough (two joins via an intermediate table). All are
                // singular, so a join yields one row per parent. Deliberately NOT
                // `isJoinable()`: it would also admit nothing extra here, but the
                // explicit instanceof list keeps mis-joinable shapes out by design.
                if ($relationInstance instanceof BelongsTo
                    || $relationInstance instanceof HasOne
                    || $relationInstance instanceof HasOneThrough) {
                    $registry->registerRelation(
                        get_class($current),
                        RelationMetadata::fromRelation($part, get_class($current), $relationInstance),
                    );
                }

                $current = $relatedModel;
            } catch (\Throwable) {
                return;
            }
        }
    }

    /**
     * Convert active filter values + Filter config → FilterDefinition[] for the planner.
     *
     * Column-level filters are not converted here; they are applied post-planner
     * via Column::applyFilter() in buildQuery().
     *
     * @param  array<int, Filter>  $filters
     * @param  array<string, mixed>  $filterValues
     * @return array<int, FilterDefinition>
     */
    private function buildPlannerFilters(
        array $filters,
        array $filterValues,
        bool $subRowsEnabled = false,
    ): array {
        $this->enrichSelectFiltersWithEnumOptions($filters);

        $definitions = [];

        // Global filters (without custom query callbacks — those are handled post-plan)
        foreach ($filters as $filter) {
            // data_get so dotted (relation) filter names resolve their nested state.
            $raw = data_get($filterValues, $filter->getName());
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

            // Delegate to the filter's own planner mapping — mirroring the column
            // header path (buildPlannerColumnFilters). A generic `=` here ignored
            // TextFilter's LIKE (and any subclass operator), so a standalone
            // ->filters([TextFilter::make(...)]) did an exact match, not a search.
            foreach ($filter->toPlannerDefinitions($value) as $definition) {
                $definitions[] = $definition;
            }
        }

        // Column header filters are added by buildPlannerColumnFilters().

        return $definitions;
    }

    /**
     * Convert active column header-filter values → FilterDefinition[] for the
     * planner. Each column delegates to its canonical Filter's
     * toPlannerDefinitions(); filters the planner cannot express (date, boolean,
     * custom callbacks) return [] and are applied post-planner via
     * Column::applyFilter() in buildQuery().
     *
     * @param  array<int, Column>  $columns
     * @param  array<string, mixed>  $columnFilterValues
     * @return array<int, FilterDefinition>
     */
    private function buildPlannerColumnFilters(array $columns, array $columnFilterValues): array
    {
        $definitions = [];

        foreach ($columns as $column) {
            $filter = $column->getFilter();
            if ($filter === null || ! $filter->canView()) {
                continue;
            }

            $value = $columnFilterValues[$column->getName()] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            foreach ($filter->toPlannerDefinitions($value) as $definition) {
                $definitions[] = $definition;
            }
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
