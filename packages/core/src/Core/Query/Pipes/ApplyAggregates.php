<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\AggregateClause;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Applies aggregate subqueries from the QueryPlan (withCount, withSum, etc.).
 */
final class ApplyAggregates implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasAggregates()) {
            return $next($builder, $plan);
        }

        foreach ($plan->aggregates as $aggregate) {
            $this->applyAggregate($builder, $aggregate);
        }

        return $next($builder, $plan);
    }

    /** @param Builder<Model> $builder */
    private function applyAggregate(Builder $builder, AggregateClause $aggregate): void
    {
        $relation = $aggregate->relation;

        match ($aggregate->function) {
            'count' => $builder->withCount([$relation.' as '.$aggregate->getAlias()]),
            'exists' => $builder->withExists([$relation.' as '.$aggregate->getAlias()]),
            'sum' => $builder->withSum($relation.' as '.$aggregate->getAlias(), $aggregate->column),
            'avg' => $builder->withAvg($relation.' as '.$aggregate->getAlias(), $aggregate->column),
            'min' => $builder->withMin($relation.' as '.$aggregate->getAlias(), $aggregate->column),
            'max' => $builder->withMax($relation.' as '.$aggregate->getAlias(), $aggregate->column),
            default => null,
        };
    }
}
