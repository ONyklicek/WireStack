<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\Concerns\EscapesLikeTerm;

/**
 * PostgreSQL search strategy using ILIKE for case-insensitive matching.
 */
final class PostgresSearchStrategy implements SearchStrategy
{
    use EscapesLikeTerm;

    /** {@inheritDoc} */
    public function apply(Builder $builder, SearchClause $clause, string $term): void
    {
        $likeTerm = $this->likeContains($term);
        $escape = " ESCAPE '".self::LIKE_ESCAPE."'";

        if ($clause->sqlExpression !== null) {
            $builder->orWhereRaw("{$clause->sqlExpression} ILIKE ?{$escape}", [$likeTerm]);

            return;
        }

        $column = $builder->getQuery()->getGrammar()->wrap($clause->getQualifiedColumn());
        $builder->orWhereRaw("{$column} ILIKE ?{$escape}", [$likeTerm]);
    }
}
