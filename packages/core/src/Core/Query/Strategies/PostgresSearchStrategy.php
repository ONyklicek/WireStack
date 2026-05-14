<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * PostgreSQL search strategy using ILIKE for case-insensitive matching.
 */
final class PostgresSearchStrategy implements SearchStrategy
{
    /** {@inheritDoc} */
    public function apply(Builder $builder, SearchClause $clause, string $term): void
    {
        $qualifiedColumn = $clause->getQualifiedColumn();
        $likeTerm = '%'.$term.'%';

        if ($clause->sqlExpression !== null) {
            $builder->orWhereRaw("{$clause->sqlExpression} ILIKE ?", [$likeTerm]);
        } else {
            $builder->orWhereRaw("{$qualifiedColumn} ILIKE ?", [$likeTerm]);
        }
    }
}
