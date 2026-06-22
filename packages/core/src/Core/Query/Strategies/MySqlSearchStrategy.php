<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\Concerns\EscapesLikeTerm;

/**
 * MySQL search strategy using LIKE with wildcards.
 *
 * MySQL LIKE is case-insensitive for non-binary columns by default.
 */
final class MySqlSearchStrategy implements SearchStrategy
{
    use EscapesLikeTerm;

    /** {@inheritDoc} */
    public function apply(Builder $builder, SearchClause $clause, string $term): void
    {
        $likeTerm = $this->likeContains($term);
        $escape = " ESCAPE '".self::LIKE_ESCAPE."'";

        if ($clause->sqlExpression !== null) {
            $builder->orWhereRaw("{$clause->sqlExpression} LIKE ?{$escape}", [$likeTerm]);

            return;
        }

        $column = $builder->getQuery()->getGrammar()->wrap($clause->getQualifiedColumn());
        $builder->orWhereRaw("{$column} LIKE ?{$escape}", [$likeTerm]);
    }
}
