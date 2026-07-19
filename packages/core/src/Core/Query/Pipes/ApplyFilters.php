<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Support\SqlSafety;

/**
 * Applies filter clauses from the QueryPlan to the builder.
 *
 * Base-table filters become plain WHERE clauses. Relation filters are applied
 * natively through whereHas()/orWhereHas() on the dot-notation relation path —
 * Eloquent owns the join keys, global scopes, and relation constraints, and it
 * handles nested paths and to-many relations that a manual JOIN could not.
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
        if ($filter->isAggregate && $filter->aggregateRelation !== null) {
            $this->applyAggregateFilter($builder, $filter);

            return;
        }

        if ($filter->isRelation && $filter->relationPath !== null) {
            $this->applyRelationFilter($builder, $filter);

            return;
        }

        // A raw SQL expression bypasses the shared comparison helper: the operator
        // is interpolated into SQL, so it must pass the operator allow-list first.
        if ($filter->sqlExpression !== null) {
            SqlSafety::assertValidOperator($filter->operator);
            $builder->whereRaw("{$filter->sqlExpression} {$filter->operator} ?", [$filter->value], $filter->boolean);

            return;
        }

        $this->applyComparison($builder, $filter->getQualifiedColumn(), $filter->operator, $filter->value, $filter->boolean);
    }

    /**
     * Apply a relation filter as whereHas()/orWhereHas() on the dot-notation
     * relation path. The comparison runs against the related model's own column
     * inside the existence subquery Eloquent builds.
     *
     * A path containing a MorphTo cannot be expressed via whereHas (there is no
     * single related model to constrain); such a filter is skipped, preserving
     * the previous behaviour of not applying it rather than throwing.
     *
     * @param  Builder<Model>  $builder
     */
    private function applyRelationFilter(Builder $builder, FilterClause $filter): void
    {
        $relationPath = $filter->relationPath;

        if (! $this->isWhereHasSafe($builder->getModel(), $relationPath)) {
            return;
        }

        $constraint = fn (Builder $related) => $this->applyComparison(
            $related,
            $filter->column,
            $filter->operator,
            $filter->value,
        );

        if ($filter->boolean === 'or') {
            $builder->orWhereHas($relationPath, $constraint);

            return;
        }

        $builder->whereHas($relationPath, $constraint);
    }

    /**
     * Apply an aggregate filter (e.g. "orders->count() > 5") as a WHERE over the
     * aggregate subquery — never as HAVING, which Postgres rejects without a
     * GROUP BY. `count` and `exists` map to Eloquent's native whereHas count
     * comparison / whereDoesntHave, so keys and the relation's scopes are honoured
     * automatically. `sum`/`avg`/`min`/`max` have no native primitive and are not
     * yet supported (they would need a correlated aggregate subquery); such a
     * filter is skipped rather than mis-applied.
     *
     * @param  Builder<Model>  $builder
     */
    private function applyAggregateFilter(Builder $builder, FilterClause $filter): void
    {
        $relation = $filter->aggregateRelation;

        if (! $this->isWhereHasSafe($builder->getModel(), $relation)) {
            return;
        }

        if ($filter->aggregateFunction === 'count') {
            $builder->whereHas($relation, null, $filter->operator, (int) $filter->value);

            return;
        }

        if ($filter->aggregateFunction === 'exists') {
            // A truthy value keeps parents that have at least one related row;
            // a falsy value keeps those with none.
            if ($filter->value) {
                $builder->whereHas($relation);
            } else {
                $builder->whereDoesntHave($relation);
            }
        }
    }

    /**
     * Whether every segment of a dot-notation relation path is a relation that
     * whereHas() can constrain — i.e. none is a MorphTo. Resolves relations from
     * the live model chain using Eloquent's own relation objects.
     */
    private function isWhereHasSafe(Model $model, string $relationPath): bool
    {
        $current = $model;

        foreach (explode('.', $relationPath) as $segment) {
            $method = Str::camel($segment);

            if (! method_exists($current, $method)) {
                return false;
            }

            try {
                $relation = $current->{$method}();
            } catch (\Throwable) {
                return false;
            }

            if (! $relation instanceof Relation || $relation instanceof MorphTo) {
                return false;
            }

            $current = $relation->getRelated();
        }

        return true;
    }

    /**
     * Apply one comparison (=, !=, LIKE, IN, BETWEEN, IS NULL, …) against a
     * column on the given builder. Shared by base-table and relation filters.
     *
     * @param  Builder<Model>  $builder
     */
    private function applyComparison(Builder $builder, string $column, string $operator, mixed $value, string $boolean = 'and'): void
    {
        $operator = strtoupper($operator);

        if ($operator === 'IS NULL') {
            $builder->whereNull($column, $boolean);

            return;
        }

        if ($operator === 'IS NOT NULL') {
            $builder->whereNotNull($column, $boolean);

            return;
        }

        if ($operator === 'IN') {
            $builder->whereIn($column, (array) $value, $boolean);

            return;
        }

        if ($operator === 'NOT IN') {
            $builder->whereNotIn($column, (array) $value, $boolean);

            return;
        }

        if ($operator === 'BETWEEN') {
            [$lower, $upper] = $this->bounds($value);

            // A genuine BETWEEN needs both bounds. With only one bound, degrade to
            // a single-sided comparison instead of "BETWEEN x AND NULL", which
            // matches nothing; with neither bound, skip the clause entirely.
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
            [$lower, $upper] = $this->bounds($value);

            // NOT BETWEEN is only well-defined with both bounds; a single bound is
            // ambiguous, so skip rather than emit "NOT BETWEEN x AND NULL".
            if ($lower !== null && $upper !== null) {
                $builder->whereNotBetween($column, [$lower, $upper], $boolean);
            }

            return;
        }

        // Direct comparison (=, !=, <, >, LIKE, …). The operator was normalised to
        // upper case above; SQL treats these tokens case-insensitively.
        $builder->where($column, $operator, $value, $boolean);
    }

    /**
     * @return array{0: mixed, 1: mixed}
     */
    private function bounds(mixed $value): array
    {
        $values = array_values((array) $value);

        return [$values[0] ?? null, $values[1] ?? null];
    }
}
