<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Applies eager loading from the QueryPlan.
 *
 * Used for morph relations, toMany relations, and other non-joinable paths.
 */
final class ApplyEagerLoads implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasEagerLoads()) {
            return $next($builder, $plan);
        }

        $builder->with($plan->eagerLoads);

        return $next($builder, $plan);
    }
}
