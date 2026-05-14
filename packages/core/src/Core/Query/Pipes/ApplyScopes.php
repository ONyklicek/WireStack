<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Applies Eloquent model scopes from the QueryPlan.
 */
final class ApplyScopes implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasScopes()) {
            return $next($builder, $plan);
        }

        foreach ($plan->scopes as $scope) {
            $builder->scopes([$scope]);
        }

        return $next($builder, $plan);
    }
}
