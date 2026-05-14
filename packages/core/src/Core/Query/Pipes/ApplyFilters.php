<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Applies filter clauses from the QueryPlan to the builder.
 */
final class ApplyFilters implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasFilters()) {
            return $next($builder, $plan);
        }

        foreach ($plan->filters as $filter) {
            $this->applyFilter($builder, $filter);
        }

        return $next($builder, $plan);
    }

    /** @param Builder<Model> $builder */
    private function applyFilter(Builder $builder, FilterClause $filter): void
    {
        $column = $filter->getQualifiedColumn();
        $boolean = $filter->boolean;

        if ($filter->isNullCheck()) {
            if ($filter->operator === 'IS NULL') {
                $builder->whereNull($column, $boolean);
            } else {
                $builder->whereNotNull($column, $boolean);
            }

            return;
        }

        $operator = strtoupper($filter->operator);

        if ($operator === 'IN') {
            $builder->whereIn($column, (array) $filter->value, $boolean);

            return;
        }

        if ($operator === 'NOT IN') {
            $builder->whereNotIn($column, (array) $filter->value, $boolean);

            return;
        }

        if ($operator === 'BETWEEN') {
            $values = (array) $filter->value;
            $builder->whereBetween($column, [$values[0] ?? null, $values[1] ?? null], $boolean);

            return;
        }

        if ($operator === 'NOT BETWEEN') {
            $values = (array) $filter->value;
            $builder->whereNotBetween($column, [$values[0] ?? null, $values[1] ?? null], $boolean);

            return;
        }

        if ($filter->sqlExpression !== null) {
            $builder->whereRaw("{$filter->sqlExpression} {$filter->operator} ?", [$filter->value], $boolean);

            return;
        }

        $builder->where($column, $filter->operator, $filter->value, $boolean);
    }
}
