<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Core\Support\SqlSafety;

/**
 * Applies sort clauses from the QueryPlan to the builder.
 */
final class ApplySorting implements QueryPipe
{
    /** {@inheritDoc} */
    public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
    {
        if (! $plan->hasSorting()) {
            return $next($builder, $plan);
        }

        foreach ($plan->sortClauses as $sort) {
            $this->applySort($builder, $sort);
        }

        return $next($builder, $plan);
    }

    /** @param Builder<Model> $builder */
    private function applySort(Builder $builder, SortClause $sort): void
    {
        $column = $sort->getQualifiedColumn();

        if ($sort->sqlExpression !== null) {
            $nullsSuffix = $sort->nullsPosition !== null ? " NULLS {$sort->nullsPosition}" : '';
            $builder->orderByRaw("{$sort->sqlExpression} {$sort->direction}{$nullsSuffix}");

            return;
        }

        if ($sort->nullsPosition !== null) {
            // The column is interpolated into raw SQL for NULLS ordering, so it
            // must be a valid identifier/qualified column (raw expressions, which
            // contain spaces or parentheses, are allowed through unchanged).
            SqlSafety::assertValidQualifiedColumn($column);
            $builder->orderByRaw("{$column} {$sort->direction} NULLS {$sort->nullsPosition}");

            return;
        }

        $builder->orderBy($column, $sort->direction);
    }
}
