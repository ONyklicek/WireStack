<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\Search\LikePattern;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * SQLite search strategy using LIKE.
 *
 * SQLite's LIKE is case-insensitive for ASCII only, and — unlike the other
 * engines — has no default escape character, which is why the predicate always
 * declares one.
 */
final class SqliteSearchStrategy implements SearchStrategy
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
