<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies;

use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Query\Contracts\SearchStrategy;
use NyonCode\WireCore\Core\Query\Search\LikePattern;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * PostgreSQL search strategy using ILIKE for case-insensitive matching.
 *
 * The column is cast to text first. PostgreSQL has no implicit cast for the
 * pattern operators, so `amount ILIKE '%50%'` on a `numeric` column is not a
 * match that fails — it is `operator does not exist: numeric ~~* text`, an
 * outright error that takes the whole page down. MySQL and SQLite coerce
 * silently, so a table that marks a number or a date searchable works
 * everywhere except PostgreSQL, and only once someone types into the box.
 *
 * The cast is unconditional rather than driven by the clause's value type: the
 * type is *inferred* (from casts, from the registered schema) and can be absent
 * or wrong, while the column's real type is what the server enforces. A cast on
 * a column that is already text costs nothing, and `ILIKE '%…%'` was never
 * going to use an index either way.
 */
final class PostgresSearchStrategy implements SearchStrategy
{
    /** {@inheritDoc} */
    public function apply(Builder $builder, SearchClause $clause, string $pattern): void
    {
        $escape = LikePattern::escapeClause();

        if ($clause->sqlExpression !== null) {
            $builder->orWhereRaw("CAST({$clause->sqlExpression} AS TEXT) ILIKE ?{$escape}", [$pattern]);

            return;
        }

        $column = $builder->getQuery()->getGrammar()->wrap($clause->getQualifiedColumn());

        $builder->orWhereRaw("CAST({$column} AS TEXT) ILIKE ?{$escape}", [$pattern]);
    }
}
