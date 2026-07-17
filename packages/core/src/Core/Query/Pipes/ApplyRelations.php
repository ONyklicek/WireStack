<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Pipes;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\JoinScope;
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

        // A join makes an unqualified `select *` ambiguous: a base column and a
        // joined column can share a name (users.name vs companies.name), and the
        // driver would pick one arbitrarily — silently rendering the wrong value.
        // Qualify the select to the base table's own columns; the joined columns
        // exist only to be sorted/filtered on, not selected (their values are
        // shown through eager loading, not this join).
        $builder->select($builder->getModel()->getTable().'.*');

        foreach ($plan->joins as $join) {
            if ($join->scope === null) {
                $builder->join(
                    "{$join->table} as {$join->alias}",
                    $join->firstColumn,
                    $join->operator,
                    $join->secondColumn,
                    $join->type,
                );

                continue;
            }

            // The joined table carries global scopes and/or relation constraints.
            // Join against a scoped subquery so they constrain the related rows
            // exactly as Eloquent would — while the LEFT JOIN stays a LEFT JOIN
            // (a parent whose related rows are all scoped away keeps NULLs).
            $builder->joinSub(
                $this->scopedSubquery($join->scope),
                $join->alias,
                $join->firstColumn,
                $join->operator,
                $join->secondColumn,
                $join->type,
            );
        }

        return $next($builder, $plan);
    }

    /**
     * Realise a {@see JoinScope} into the Eloquent query used as the joined
     * subquery. Returning the Eloquent builder (not its base query) is what makes
     * `joinSub` apply the model's global scopes — they are compiled in `toSql()`,
     * not stored on the underlying query builder.
     *
     * `relationParent` rebuilds the relation without its parent-key binding so
     * the query keeps global scopes AND the relation method's own constraints;
     * otherwise only the model's global scopes apply.
     *
     * @return Builder<Model>
     */
    private function scopedSubquery(JoinScope $scope): Builder
    {
        if ($scope->relationParent !== null) {
            $parent = $scope->relationParent;
            $method = $scope->relationMethod;
            $relation = Relation::noConstraints(fn () => (new $parent)->{$method}());

            if ($relation instanceof Relation) {
                return $relation->getQuery();
            }
        }

        return $scope->model::query();
    }
}
