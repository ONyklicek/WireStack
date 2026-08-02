<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\Search\LikePattern;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * MySQL/MariaDB search strategy using LIKE.
 *
 * LIKE is case-insensitive for non-binary collations, which is what a search
 * box is expected to do, so no normalisation is applied on top.
 */
final class MySqlSearchStrategy implements SearchStrategy
{
    /** {@inheritDoc} */
    public function apply(Builder $builder, SearchClause $clause, string $pattern): void
    {
        $escape = LikePattern::escapeClause();

        if ($clause->sqlExpression !== null) {
            $builder->orWhereRaw("{$clause->sqlExpression} LIKE ?{$escape}", [$pattern]);

            return;
        }

        $column = $builder->getQuery()->getGrammar()->wrap($clause->getQualifiedColumn());

        $builder->orWhereRaw("{$column} LIKE ?{$escape}", [$pattern]);
    }
}
