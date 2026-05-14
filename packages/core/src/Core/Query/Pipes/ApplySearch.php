<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Applies search clauses from the QueryPlan using a database-specific strategy.
 *
 * All search clauses are combined with OR inside a grouped WHERE.
 */
final class ApplySearch implements QueryPipe
{
    public function __construct(
        private readonly SearchStrategy $strategy,
        private readonly ?string $searchTerm = null,
    ) {}

    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasSearch() || $this->searchTerm === null || $this->searchTerm === '') {
            return $next($builder, $plan);
        }

        $strategy = $this->strategy;
        $term = $this->searchTerm;

        $builder->where(function (Builder $query) use ($plan, $strategy, $term): void {
            foreach ($plan->searchClauses as $clause) {
                $strategy->apply($query, $clause, $term);
            }
        });

        return $next($builder, $plan);
    }
}
