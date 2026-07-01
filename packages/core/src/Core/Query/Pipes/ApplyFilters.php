<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Support\SqlSafety;

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
            $values = array_values((array) $filter->value);
            $lower = $values[0] ?? null;
            $upper = $values[1] ?? null;

            // A genuine BETWEEN needs both bounds. With only one bound, degrade to a
            // single-sided comparison instead of "BETWEEN x AND NULL", which matches
            // nothing; with neither bound, skip the clause entirely.
            if ($lower !== null && $upper !== null) {
                $builder->whereBetween($column, [$lower, $upper], $boolean);
            } elseif ($lower !== null) {
                $builder->where($column, '>=', $lower, $boolean);
            } elseif ($upper !== null) {
                $builder->where($column, '<=', $upper, $boolean);
            }

            return;
        }

        if ($operator === 'NOT BETWEEN') {
            $values = array_values((array) $filter->value);
            $lower = $values[0] ?? null;
            $upper = $values[1] ?? null;

            // NOT BETWEEN is only well-defined with both bounds; a single bound is
            // ambiguous, so skip rather than emit "NOT BETWEEN x AND NULL".
            if ($lower !== null && $upper !== null) {
                $builder->whereNotBetween($column, [$lower, $upper], $boolean);
            }

            return;
        }

        if ($filter->sqlExpression !== null) {
            // The operator is interpolated into raw SQL here, so it must pass the
            // canonical operator allow-list before use (the value stays bound).
            SqlSafety::assertValidOperator($filter->operator);
            $builder->whereRaw("{$filter->sqlExpression} {$filter->operator} ?", [$filter->value], $boolean);

            return;
        }

        $builder->where($column, $filter->operator, $filter->value, $boolean);
    }
}
