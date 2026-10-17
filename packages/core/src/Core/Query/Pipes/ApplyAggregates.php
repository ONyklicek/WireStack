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
        $relation = $aggregate->relation.' as '.$aggregate->getAlias();
        $column = $aggregate->column;

        match ($aggregate->function) {
            'count' => $builder->withCount([$relation]),
            'exists' => $builder->withExists([$relation]),
            default => null,
        };

        // sum/avg/min/max aggregate a column; without one there is nothing to add.
        if ($column === null) {
            return;
        }

        match ($aggregate->function) {
            'sum' => $builder->withSum($relation, $column),
            'avg' => $builder->withAvg($relation, $column),
            'min' => $builder->withMin($relation, $column),
            'max' => $builder->withMax($relation, $column),
            default => null,
        };
    }
}
