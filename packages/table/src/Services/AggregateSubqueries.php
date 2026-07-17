<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;

/**
 * Applies the `withCount` / `withSum` / `withAvg` / `withMin` / `withMax`
 * subqueries that back a column's rollup value.
 *
 * The same five-arm map was written twice: here (from `TableQueryService`) and
 * inline in `WithTable::getSelectedRecords()`, whose comment said outright that
 * it "applies the same withCount/withSum aggregate subqueries that
 * buildTableQuery() adds". Without it a rollup column's attribute is simply
 * absent, and a summary over it plucks nothing and renders 0 — a wrong number
 * rather than an error, which is why it was copied rather than shared.
 */
final class AggregateSubqueries
{
    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  Closure|null  $subRowConstraint  Constrains the aggregate the same way the
     *                                          displayed children are, so a filtered sub-row
     *                                          table and its rollup agree. The constrained
     *                                          array syntax keeps the default alias
     *                                          (e.g. items_sum_total).
     * @return Builder<Model>
     */
    public function apply(
        Builder $query,
        array $columns,
        ?string $subRowRelation = null,
        ?Closure $subRowConstraint = null,
    ): Builder {
        foreach ($columns as $column) {
            if (! $column->isAggregate()) {
                continue;
            }

            $relation = $column->getAggregateRelation();
            $aggregateCol = $column->getAggregateColumn();

            $target = ($subRowConstraint !== null && $relation === $subRowRelation)
                ? [$relation => $subRowConstraint]
                : $relation;

            match ($column->getAggregateFunction()) {
                'count' => $query->withCount($target),
                'sum' => $query->withSum($target, $aggregateCol),
                'avg' => $query->withAvg($target, $aggregateCol),
                'min' => $query->withMin($target, $aggregateCol),
                'max' => $query->withMax($target, $aggregateCol),
                default => null,
            };
        }

        return $query;
    }
}
