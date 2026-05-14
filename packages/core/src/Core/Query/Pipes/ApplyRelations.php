<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Applies JOIN clauses from the QueryPlan to the builder.
 *
 * Each JoinClause is registered as a LEFT/INNER join with alias.
 */
final class ApplyRelations implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasJoins()) {
            return $next($builder, $plan);
        }

        foreach ($plan->joins as $join) {
            $builder->join(
                "{$join->table} as {$join->alias}",
                $join->firstColumn,
                $join->operator,
                $join->secondColumn,
                $join->type,
            );
        }

        return $next($builder, $plan);
    }
}
