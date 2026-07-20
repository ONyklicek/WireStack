<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\Pipes\ApplyAggregates;
use NyonCode\WireCore\Core\Query\Pipes\ApplyEagerLoads;
use NyonCode\WireCore\Core\Query\Pipes\ApplyFilters;
use NyonCode\WireCore\Core\Query\Pipes\ApplyRelations;
use NyonCode\WireCore\Core\Query\Pipes\ApplyScopes;
use NyonCode\WireCore\Core\Query\Pipes\ApplySearch;
use NyonCode\WireCore\Core\Query\Pipes\ApplySoftDeletes;
use NyonCode\WireCore\Core\Query\Pipes\ApplySorting;
use NyonCode\WireCore\Core\Query\Strategies\MySqlSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\PostgresSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\SqliteSearchStrategy;
use NyonCode\WireCore\Core\Support\DriverDetector;

/**
 * Executes a QueryPlan against an Eloquent Builder using a pipeline of QueryPipes.
 *
 * The executor takes an immutable QueryPlan (built by QueryPlanner) and applies
 * each aspect (joins, filters, search, sorting, aggregates, etc.) through
 * individual pipe steps.
 */
final class QueryExecutor
{
    /** @var array<int, QueryPipe> */
    private array $pipes = [];

    private ?SearchStrategy $searchStrategy = null;

    /**
     * Execute a QueryPlan against the builder.
     *
     * Returns the modified builder (not yet executed — caller decides when to ->get()).
     *
     * @param  Builder<Model>  $builder
     * @param  array<int, callable(Builder<Model>, string): mixed>  $extraSearchCallbacks
     * @return Builder<Model>
     */
    public function execute(Builder $builder, QueryPlan $plan, ?string $searchTerm = null, array $extraSearchCallbacks = []): Builder
    {
        $pipes = $this->pipes !== [] ? $this->pipes : $this->getDefaultPipes($builder, $searchTerm, $extraSearchCallbacks);

        // Build the pipeline from the end — last pipe calls no-op, second-to-last calls last, etc.
        $pipeline = array_reduce(
            array_reverse($pipes),
            fn ($next, QueryPipe $pipe) => fn (Builder $b, QueryPlan $p) => $pipe->handle($b, $p, $next),
            fn (Builder $b, QueryPlan $p) => $b,
        );

        return $pipeline($builder, $plan);
    }

    /**
     * Override the default pipeline with custom pipes.
     *
     * @param  array<int, QueryPipe>  $pipes
     */
    public function withPipes(array $pipes): self
    {
        $clone = clone $this;
        $clone->pipes = $pipes;

        return $clone;
    }

    /**
     * Set a custom search strategy.
     */
    public function withSearchStrategy(SearchStrategy $strategy): self
    {
        $clone = clone $this;
        $clone->searchStrategy = $strategy;

        return $clone;
    }

    /**
     * Get the default pipeline of pipes.
     *
     * Order matters:
     * 1. Scopes — apply model scopes first
     * 2. SoftDeletes — modify scope before joins
     * 3. Relations — joins must be in place before filters/search reference them
     * 4. Search — search across columns (including joined)
     * 5. Filters — apply WHERE clauses
     * 6. Sorting — ORDER BY
     * 7. Aggregates — withCount, withSum, etc.
     * 8. EagerLoads — with() for non-joinable relations
     *
     * @param  Builder<Model>  $builder
     * @param  array<int, callable(Builder<Model>, string): mixed>  $extraSearchCallbacks
     * @return array<int, QueryPipe>
     */
    public function getDefaultPipes(Builder $builder, ?string $searchTerm, array $extraSearchCallbacks = []): array
    {
        $strategy = $this->searchStrategy ?? $this->resolveSearchStrategy($builder);

        return [
            new ApplyScopes,
            new ApplySoftDeletes,
            new ApplyRelations,
            new ApplySearch($strategy, $searchTerm, $extraSearchCallbacks),
            new ApplyFilters,
            new ApplySorting,
            new ApplyAggregates,
            new ApplyEagerLoads,
        ];
    }

    /**
     * Auto-detect the search strategy based on the database driver.
     *
     * @param  Builder<Model>  $builder
     */
    private function resolveSearchStrategy(Builder $builder): SearchStrategy
    {
        $driver = DriverDetector::fromBuilder($builder);

        if (DriverDetector::isPostgres($driver)) {
            return new PostgresSearchStrategy;
        }

        if (DriverDetector::isMysql($driver)) {
            return new MySqlSearchStrategy;
        }

        return new SqliteSearchStrategy;
    }
}
