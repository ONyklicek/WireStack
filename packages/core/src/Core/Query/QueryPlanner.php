<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Components\DataComponent;
use NyonCode\WireCore\Core\Metadata\MetadataRegistry;
use NyonCode\WireCore\Core\Metadata\RelationMetadata;
use NyonCode\WireCore\Core\Query\Contracts\HasSearchColumns;
use NyonCode\WireCore\Core\Query\Strategies\MorphRelationStrategy;
use NyonCode\WireCore\Core\Relations\AggregateSegment;
use NyonCode\WireCore\Core\Relations\RelationGraphBuilder;
use NyonCode\WireCore\Core\Relations\RelationPath;

/**
 * Analyzes columns, filters, sorting, and search configuration to produce a QueryPlan.
 *
 * The planner does NOT generate SQL — it only analyzes and plans.
 * The result is an immutable QueryPlan consumed by QueryExecutor (Phase 2).
 */
final class QueryPlanner
{
    private readonly MorphRelationStrategy $morphStrategy;

    public function __construct(
        private readonly MetadataRegistry $metadataRegistry,
        private readonly JoinRegistry $joinRegistry,
        ?MorphRelationStrategy $morphStrategy = null,
    ) {
        $this->morphStrategy = $morphStrategy ?? new MorphRelationStrategy($this->metadataRegistry);
    }

    /**
     * Analyze all inputs and produce a QueryPlan.
     *
     * @param  string  $modelClass  The base model class
     * @param  array<int, DataComponent>  $columns  Components to display/query
     * @param  array<int, FilterDefinition>  $filters  Active filters with values
     * @param  array<int, SortDefinition>  $sorts  Active sorts
     * @param  string|null  $search  Global search term
     * @param  array<int, string>  $scopes  Model scopes to apply
     * @param  bool  $withSoftDeletes  Include soft-deleted records
     */
    public function plan(
        string $modelClass,
        array $columns = [],
        array $filters = [],
        array $sorts = [],
        ?string $search = null,
        array $scopes = [],
        bool $withSoftDeletes = false,
    ): QueryPlan {
        $this->joinRegistry->reset();

        $modelMetadata = $this->metadataRegistry->getModelMetadata($modelClass);
        $baseTable = $modelMetadata->table;

        $graphBuilder = new RelationGraphBuilder;

        $eagerLoads = [];
        $aggregates = [];
        $filterClauses = [];
        $searchClauses = [];
        $sortClauses = [];

        // 1. Analyze columns — determine joins, eager loads, aggregates, searchable columns
        foreach ($columns as $component) {
            $path = $component->getRelationPath();

            if ($path === null) {
                // Simple column on base table
                $this->planSimpleColumn($component, $baseTable, $search, $searchClauses);

                continue;
            }

            $graphBuilder->addPath($path);

            if ($path->isAggregate()) {
                $this->planAggregateColumn($path, $aggregates);

                continue;
            }

            if ($path->hasRelation()) {
                $this->planRelationColumn(
                    $component, $path, $modelClass, $baseTable,
                    $search, $eagerLoads, $searchClauses,
                );
            }
        }

        // 2. Analyze filters
        foreach ($filters as $filter) {
            $this->planFilter($filter, $modelClass, $baseTable, $eagerLoads, $filterClauses);
        }

        // 3. Analyze sorting
        foreach ($sorts as $sort) {
            $this->planSort($sort, $modelClass, $baseTable, $sortClauses);
        }

        $relationGraph = $graphBuilder->build();

        // 4. Collect eager loads from relation graph (for morph/toMany relations)
        $graphEagerLoads = $relationGraph->getEagerLoadPaths();
        $eagerLoads = array_values(array_unique([...$eagerLoads, ...$graphEagerLoads]));

        return new QueryPlan(
            joins: array_values($this->joinRegistry->getAllJoins()),
            eagerLoads: $eagerLoads,
            aggregates: $aggregates,
            filters: $filterClauses,
            searchClauses: $searchClauses,
            sortClauses: $sortClauses,
            scopes: $scopes,
            relationGraph: $relationGraph,
            withSoftDeletes: $withSoftDeletes,
        );
    }

    /**
     * @param  array<int, SearchClause>  $searchClauses
     */
    private function planSimpleColumn(
        DataComponent $component,
        string $baseTable,
        ?string $search,
        array &$searchClauses,
    ): void {
        $columnName = $component->getColumnName();
        $metadata = $component->getColumnMetadata();

        // Search planning
        if ($search !== null && $component->hasCapability(Capability::Searchable)) {
            $sqlExpression = $metadata?->sqlExpression;

            // A composite column (a stacked name-over-email cell) is searched
            // across the columns it actually shows; an ordinary one across its
            // own. The strategies OR the clauses inside one where(), so several
            // clauses widen the match rather than narrowing it.
            $searchColumns = $component instanceof HasSearchColumns && $component->getSearchColumns() !== []
                ? $component->getSearchColumns()
                : [$columnName];

            foreach ($searchColumns as $searchColumn) {
                $searchClauses[] = new SearchClause(
                    column: $searchColumn,
                    // A sqlExpression describes the component's own column, so it
                    // cannot speak for a different one.
                    tableAlias: $baseTable,
                    sqlExpression: $searchColumn === $columnName ? $sqlExpression : null,
                );
            }
        }
    }

    /**
     * @param  array<int, AggregateClause>  $aggregates
     */
    private function planAggregateColumn(
        RelationPath $path,
        array &$aggregates,
    ): void {
        $terminal = $path->getTerminal();
        if (! $terminal instanceof AggregateSegment) {
            return;
        }

        $aggregates[] = new AggregateClause(
            relation: $terminal->relation,
            function: $terminal->function,
            column: $terminal->column,
            strategy: AggregateClause::resolveStrategy($terminal->function, true),
        );
    }

    /**
     * @param  array<int, string>  $eagerLoads
     * @param  array<int, SearchClause>  $searchClauses
     */
    private function planRelationColumn(
        DataComponent $component,
        RelationPath $path,
        string $modelClass,
        string $baseTable,
        ?string $search,
        array &$eagerLoads,
        array &$searchClauses,
    ): void {
        $relationSegments = $path->getRelationSegments();
        $columnName = $path->getColumnName();
        $relationPath = $path->getRelationPath();

        // Morph relations cannot be joined — eager load for display.
        if ($this->morphStrategy->isMorphPath($path, $modelClass)) {
            $morphEagerLoads = $this->morphStrategy->getEagerLoadPaths($path);
            $eagerLoads = [...$eagerLoads, ...$morphEagerLoads];

            return;
        }

        // Display value is read via data_get($record, 'company.name'), so the
        // relation is eager-loaded. This replaces relying on a JOIN's selected
        // column (which was never actually selected — ApplyRelations qualifies the
        // select to base.*) and avoids an N+1 lazy load at render time.
        if ($relationPath !== null) {
            $eagerLoads[] = $relationPath;
        }

        // A relation column only needs a JOIN when it is *searched*: the search
        // clause references the joined alias. Sorting registers its own join in
        // planSort(); plain display (above) and filtering (whereHas) do not join.
        if ($search === null || ! $component->hasCapability(Capability::Searchable)) {
            return;
        }

        // Search requires the singular, joinable relation chain to be joined.
        $currentModel = $modelClass;
        $relationNames = [];
        $canJoin = true;

        foreach ($relationSegments as $segment) {
            $segmentName = $segment->getName();
            $relationNames[] = $segmentName;
            $relation = $this->metadataRegistry->getRelation($currentModel, $segmentName);

            if ($relation === null || ! $relation->isJoinable()) {
                $canJoin = false;

                break;
            }

            $currentModel = $relation->relatedModel;
        }

        // A to-many relation cannot be join-searched; its column is already
        // eager-loaded for display, matching the previous behaviour.
        if (! $canJoin || $currentModel === null) {
            return;
        }

        $alias = $this->registerRelationJoins($modelClass, $baseTable, $relationNames);

        if ($alias !== null) {
            $searchClauses[] = new SearchClause(
                column: $columnName,
                tableAlias: $alias,
                isRelation: true,
                relationPath: $relationPath,
            );
        }
    }

    /**
     * @param  array<int, string>  $eagerLoads
     * @param  array<int, FilterClause>  $filterClauses
     */
    private function planFilter(
        FilterDefinition $filter,
        string $modelClass,
        string $baseTable,
        array &$eagerLoads,
        array &$filterClauses,
    ): void {
        $path = $filter->relationPath;

        if ($path === null) {
            // Simple filter on base table
            $filterClauses[] = new FilterClause(
                column: $filter->column,
                operator: $filter->operator,
                value: $filter->value,
                tableAlias: $baseTable,
                sqlExpression: $filter->sqlExpression,
            );

            return;
        }

        // Aggregate filter (e.g. "orders->count()" > 5). Applied in ApplyFilters as
        // a WHERE over the aggregate subquery (whereHas count) — never as HAVING,
        // which is not cross-engine safe (Postgres rejects it without GROUP BY).
        if ($path->isAggregate()) {
            $terminal = $path->getTerminal();
            if ($terminal instanceof AggregateSegment) {
                $filterClauses[] = new FilterClause(
                    column: $terminal->getName(),
                    operator: $filter->operator,
                    value: $filter->value,
                    isAggregate: true,
                    aggregateRelation: $terminal->relation,
                    aggregateFunction: $terminal->function,
                );
            }

            return;
        }

        // Relation filter — applied natively via whereHas() in ApplyFilters, not
        // through a JOIN. Eloquent supplies the relation keys, global scopes, and
        // any method constraints, and it handles nested dot paths and to-many
        // relations (a join could not). No alias is registered here.
        //
        // Morph relations still contribute a display eager-load for parity when
        // a filter references a relation no display column pulled in.
        if ($this->morphStrategy->isMorphPath($path, $modelClass)) {
            $relationPathStr = $path->getRelationPath();
            if ($relationPathStr !== null) {
                $eagerLoads[] = $relationPathStr;
            }
        }

        $filterClauses[] = new FilterClause(
            column: $path->getColumnName(),
            operator: $filter->operator,
            value: $filter->value,
            isRelation: true,
            relationPath: $path->getRelationPath(),
        );
    }

    /**
     * @param  array<int, SortClause>  $sortClauses
     */
    private function planSort(
        SortDefinition $sort,
        string $modelClass,
        string $baseTable,
        array &$sortClauses,
    ): void {
        if ($sort->relationPath === null) {
            // Simple sort on base table
            $sortClauses[] = new SortClause(
                column: $sort->column,
                direction: $sort->direction,
                tableAlias: $baseTable,
                sqlExpression: $sort->sqlExpression,
            );

            return;
        }

        // Relation sort — morph relations cannot be sorted via SQL
        if ($this->morphStrategy->isMorphPath($sort->relationPath, $modelClass)) {
            return;
        }

        $relationSegments = $sort->relationPath->getRelationSegments();
        $relationNames = array_map(fn ($s) => $s->getName(), $relationSegments);

        $alias = $this->registerRelationJoins($modelClass, $baseTable, $relationNames);

        if ($alias !== null) {
            $sortClauses[] = new SortClause(
                column: $sort->relationPath->getColumnName(),
                direction: $sort->direction,
                tableAlias: $alias,
                isRelation: true,
            );
        }
    }

    /**
     * Register joins for a relation chain and return the final table alias.
     *
     * @param  array<int, string>  $relationNames
     */
    private function registerRelationJoins(
        string $modelClass,
        string $baseTable,
        array $relationNames,
    ): ?string {
        $currentModel = $modelClass;
        $currentTable = $baseTable;
        $pathSoFar = [];
        $lastAlias = null;

        foreach ($relationNames as $relationName) {
            $pathSoFar[] = $relationName;
            $relation = $this->metadataRegistry->getRelation($currentModel, $relationName);

            if ($relation === null || ! $relation->isJoinable()) {
                return null;
            }

            $relatedMetadata = $this->metadataRegistry->hasModel($relation->relatedModel)
                ? $this->metadataRegistry->getModelMetadata($relation->relatedModel)
                : null;

            $relatedTable = $relatedMetadata !== null
                ? $relatedMetadata->table
                : $this->guessTableName($relation->relatedModel);

            $lastAlias = $this->registerJoinForRelation(
                $relation, $baseTable, $pathSoFar, $currentTable, $relatedTable,
            );

            $currentModel = $relation->relatedModel;
            $currentTable = $lastAlias;
        }

        return $lastAlias;
    }

    /**
     * @param  array<int, string>  $pathSoFar
     */
    private function registerJoinForRelation(
        RelationMetadata $relation,
        string $baseTable,
        array $pathSoFar,
        string $currentTableOrAlias,
        string $relatedTable,
    ): string {
        // Pre-compute the alias so we can reference it in join columns
        $alias = $this->joinRegistry->getAliasGenerator()->generate($baseTable, $pathSoFar);

        if ($relation->isThrough()) {
            return $this->registerThroughJoin($relation, $baseTable, $pathSoFar, $currentTableOrAlias, $relatedTable, $alias);
        }

        if ($relation->type === 'BelongsTo') {
            // BelongsTo: parent.foreign_key = related.local_key (usually related PK)
            $localKey = $relation->localKey ?? 'id';

            return $this->joinRegistry->registerJoin(
                baseTable: $baseTable,
                relationPath: $pathSoFar,
                joinTable: $relatedTable,
                firstColumn: "{$currentTableOrAlias}.{$relation->foreignKey}",
                operator: '=',
                secondColumn: "{$alias}.{$localKey}",
                type: 'left',
                scope: $relation->scope,
            );
        }

        // HasOne: parent.local_key = related.foreign_key
        $localKey = $relation->localKey ?? 'id';

        return $this->joinRegistry->registerJoin(
            baseTable: $baseTable,
            relationPath: $pathSoFar,
            joinTable: $relatedTable,
            firstColumn: "{$currentTableOrAlias}.{$localKey}",
            operator: '=',
            secondColumn: "{$alias}.{$relation->foreignKey}",
            type: 'left',
            scope: $relation->scope,
        );
    }

    /**
     * Register the two joins a HasOneThrough needs: base -> intermediate, then
     * intermediate -> far. The intermediate gets its own synthetic alias so a
     * second reference to the same relation (e.g. sort + filter) still dedupes,
     * and it is registered first so the far join can reference it.
     *
     * @param  array<int, string>  $pathSoFar
     */
    private function registerThroughJoin(
        RelationMetadata $relation,
        string $baseTable,
        array $pathSoFar,
        string $currentTableOrAlias,
        string $relatedTable,
        string $farAlias,
    ): string {
        $localKey = $relation->localKey ?? 'id';

        // Intermediate join: through.first_key = base.local_key
        $throughPath = [...$pathSoFar, '__via'];
        $throughAlias = $this->joinRegistry->getAliasGenerator()->generate($baseTable, $throughPath);
        $this->joinRegistry->registerJoin(
            baseTable: $baseTable,
            relationPath: $throughPath,
            joinTable: (string) $relation->throughTable,
            firstColumn: "{$throughAlias}.{$relation->firstKey}",
            operator: '=',
            secondColumn: "{$currentTableOrAlias}.{$localKey}",
            type: 'left',
            scope: $relation->throughScope,
        );

        // Far join: far.foreign_key = through.second_local_key
        return $this->joinRegistry->registerJoin(
            baseTable: $baseTable,
            relationPath: $pathSoFar,
            joinTable: $relatedTable,
            firstColumn: "{$farAlias}.{$relation->foreignKey}",
            operator: '=',
            secondColumn: "{$throughAlias}.{$relation->secondLocalKey}",
            type: 'left',
            scope: $relation->scope,
        );
    }

    /**
     * Guess table name from model class (fallback when model not registered in registry).
     *
     * Uses Laravel's Str::snake + Str::plural to match Eloquent's own convention,
     * producing correct forms like "categories" (not "categorys") and "lives" (not "lifes").
     */
    private function guessTableName(string $modelClass): string
    {
        return Str::plural(Str::snake(class_basename($modelClass)));
    }
}
