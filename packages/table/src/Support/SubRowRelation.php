<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A table's sub-row relation, opened: the child query plus the two key names
 * needed to tie children back to a parent set.
 *
 * The keys travel with the query because they come from the Eloquent relation
 * rather than from the table, so a caller that has the query but not the keys
 * would have to resolve the relation a second time to get them.
 */
final readonly class SubRowRelation
{
    /**
     * @param  Builder<Model>  $children  Every child of this relation, unrestricted.
     * @param  string  $foreignKey  Qualified — a child query commonly joins.
     * @param  string  $localKey  Unqualified: it names a column on the parent.
     */
    public function __construct(
        public Builder $children,
        public string $foreignKey,
        public string $localKey,
    ) {}
}
