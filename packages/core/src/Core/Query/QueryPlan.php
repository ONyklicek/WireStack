<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use NyonCode\WireCore\Core\Relations\RelationGraph;

/**
 * Immutable representation of a planned query.
 *
 * Contains all information needed to execute a query without generating SQL itself.
 * Built by QueryPlanner, consumed by QueryExecutor (Phase 2).
 */
final readonly class QueryPlan
{
    /**
     * @param  array<int, JoinClause>  $joins
     * @param  array<int, string>  $eagerLoads
     * @param  array<int, AggregateClause>  $aggregates
     * @param  array<int, FilterClause>  $filters
     * @param  array<int, SearchClause>  $searchClauses
     * @param  array<int, SortClause>  $sortClauses
     * @param  array<int, string>  $selectedColumns
     * @param  array<int, string>  $scopes
     * @param  bool  $withSoftDeletes  Whether to include soft-deleted records
     */
    public function __construct(
        public array $joins = [],
        public array $eagerLoads = [],
        public array $aggregates = [],
        public array $filters = [],
        public array $searchClauses = [],
        public array $sortClauses = [],
        public array $selectedColumns = [],
        public array $scopes = [],
        public ?RelationGraph $relationGraph = null,
        public bool $withSoftDeletes = false,
    ) {}

    public function hasJoins(): bool
    {
        return $this->joins !== [];
    }

    public function hasEagerLoads(): bool
    {
        return $this->eagerLoads !== [];
    }

    public function hasAggregates(): bool
    {
        return $this->aggregates !== [];
    }

    public function hasFilters(): bool
    {
        return $this->filters !== [];
    }

    public function hasSearch(): bool
    {
        return $this->searchClauses !== [];
    }

    public function hasSorting(): bool
    {
        return $this->sortClauses !== [];
    }

    public function hasScopes(): bool
    {
        return $this->scopes !== [];
    }

    public function isEmpty(): bool
    {
        return ! $this->hasJoins()
            && ! $this->hasEagerLoads()
            && ! $this->hasAggregates()
            && ! $this->hasFilters()
            && ! $this->hasSearch()
            && ! $this->hasSorting()
            && ! $this->hasScopes();
    }

    /**
     * Create a new QueryPlan with additional joins merged in.
     *
     * @param  array<int, JoinClause>  $joins
     */
    public function withJoins(array $joins): self
    {
        return new self(
            joins: [...$this->joins, ...$joins],
            eagerLoads: $this->eagerLoads,
            aggregates: $this->aggregates,
            filters: $this->filters,
            searchClauses: $this->searchClauses,
            sortClauses: $this->sortClauses,
            selectedColumns: $this->selectedColumns,
            scopes: $this->scopes,
            relationGraph: $this->relationGraph,
            withSoftDeletes: $this->withSoftDeletes,
        );
    }

    /**
     * Create a new QueryPlan with additional eager loads merged in.
     *
     * @param  array<int, string>  $eagerLoads
     */
    public function withEagerLoads(array $eagerLoads): self
    {
        return new self(
            joins: $this->joins,
            eagerLoads: array_values(array_unique([...$this->eagerLoads, ...$eagerLoads])),
            aggregates: $this->aggregates,
            filters: $this->filters,
            searchClauses: $this->searchClauses,
            sortClauses: $this->sortClauses,
            selectedColumns: $this->selectedColumns,
            scopes: $this->scopes,
            relationGraph: $this->relationGraph,
            withSoftDeletes: $this->withSoftDeletes,
        );
    }

    /**
     * Create a new QueryPlan with additional aggregates merged in.
     *
     * @param  array<int, AggregateClause>  $aggregates
     */
    public function withAggregates(array $aggregates): self
    {
        return new self(
            joins: $this->joins,
            eagerLoads: $this->eagerLoads,
            aggregates: [...$this->aggregates, ...$aggregates],
            filters: $this->filters,
            searchClauses: $this->searchClauses,
            sortClauses: $this->sortClauses,
            selectedColumns: $this->selectedColumns,
            scopes: $this->scopes,
            relationGraph: $this->relationGraph,
            withSoftDeletes: $this->withSoftDeletes,
        );
    }
}
