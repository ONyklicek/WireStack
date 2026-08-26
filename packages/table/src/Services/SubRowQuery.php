<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use NyonCode\WireTable\Support\SubRowRelation;
use NyonCode\WireTable\Table;

/**
 * Where a table's children come from.
 *
 * The counterpart to {@see SubRowFilters}, which owns *which* children a filter
 * leaves standing. This owns the query they are drawn from, and the one rule
 * that decides whether there is one at all: only a direct parent→child relation
 * can be turned into a query over children, because only there is there a
 * foreign key on the child pointing back.
 *
 * Takes the table and nothing else, so the rule can be exercised without a
 * Livewire host — the same shape {@see SubRowFilters} uses.
 */
final class SubRowQuery
{
    /**
     * Open the table's sub-row relation, or null when it has none this can use.
     *
     * Null covers two different situations on purpose, because the caller
     * handles them identically: no sub-row relation configured at all, and one
     * configured as something other than HasMany/HasOne (or their morph
     * variants) — a BelongsToMany has no foreign key on the child to restrict
     * by, so grand totals over it cannot be expressed this way.
     *
     * A morph relation additionally constrains the child's type column, without
     * which the totals would sweep in every other parent type sharing the table.
     */
    public function open(Table $table): ?SubRowRelation
    {
        $relationName = $table->getSubRowRelation();

        if ($relationName === null) {
            return null;
        }

        $relation = $table->getQuery()->getModel()->{$relationName}();

        if (! $relation instanceof HasOneOrMany) {
            return null;
        }

        $children = $relation->getRelated()->newQuery();

        if ($relation instanceof MorphOneOrMany) {
            $children->where($relation->getQualifiedMorphType(), $relation->getMorphClass());
        }

        return new SubRowRelation(
            $children,
            $relation->getQualifiedForeignKeyName(),
            $relation->getLocalKeyName(),
        );
    }
}
