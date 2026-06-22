<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * SQLite search strategy using LIKE.
 *
 * SQLite LIKE is case-insensitive for ASCII by default.
 */
final class SqliteSearchStrategy implements SearchStrategy
{
    /** {@inheritDoc} */
    public function apply(Builder $builder, SearchClause $clause, string $term): void
    {
        $qualifiedColumn = $clause->getQualifiedColumn();
        $likeTerm = '%'.$term.'%';

        if ($clause->sqlExpression !== null) {
            $builder->orWhereRaw("{$clause->sqlExpression} LIKE ?", [$likeTerm]);
        } else {
            $builder->orWhere($qualifiedColumn, 'LIKE', $likeTerm);
        }
    }
}
