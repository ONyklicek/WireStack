<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
    /**
     * @param  array<int, callable(Builder<Model>, string): mixed>  $extraCallbacks
     *                                                                               Host-supplied per-column search callbacks, OR-combined into the same group
     *                                                                               as the plan clauses so a custom-search column never suppresses the default
     *                                                                               ones.
     */
    public function __construct(
        private readonly SearchStrategy $strategy,
        private readonly ?string $searchTerm = null,
        private readonly array $extraCallbacks = [],
    ) {}

    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        $hasClauses = $plan->hasSearch() || $this->extraCallbacks !== [];

        if (! $hasClauses || $this->searchTerm === null || $this->searchTerm === '') {
            return $next($builder, $plan);
        }

        $strategy = $this->strategy;
        $term = $this->searchTerm;
        $extraCallbacks = $this->extraCallbacks;

        $builder->where(function (Builder $query) use ($plan, $strategy, $term, $extraCallbacks): void {
            foreach ($plan->searchClauses as $clause) {
                $strategy->apply($query, $clause, $term);
            }

            // Custom column callbacks share this OR group, so default-column
            // matches and custom matches combine rather than one dropping the other.
            foreach ($extraCallbacks as $callback) {
                $query->orWhere(function (Builder $sub) use ($callback, $term): void {
                    $callback($sub, $term);
                });
            }
        });

        return $next($builder, $plan);
    }
}
