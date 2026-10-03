<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\SearchStrategies;

/**
 * A search box over a handful of columns, word by word.
 *
 * The table has its own planner for this; everything else that searches — the
 * palette, a relationship select, a module's own list — has a builder and a few
 * column names and used to write `where(col, 'like', "%{$term}%")` by hand.
 * That matched the whole term as one substring (so `novak praha` found nothing
 * when the name and the city sat in different columns), left `%` and `_` acting
 * as wildcards, and said `LIKE` on PostgreSQL, where it is case-sensitive.
 *
 * This is the one place those callers go instead: the term is read by the same
 * {@see SearchTermParser} the table uses, every word must match (AND), and each
 * word may match in any of the columns (OR). Accents follow the connection's
 * collation, as everywhere the stack searches SQL.
 */
final class WordSearch
{
    public function __construct(
        private readonly SearchTermParser $parser,
    ) {}

    /**
     * Constrain the query to rows where every word of the term is found in at
     * least one of the columns.
     *
     * The constraint is nested, so it cannot leak out of whatever the caller
     * has already put on the builder. A blank term changes nothing.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $columns
     */
    public function apply(Builder $query, array $columns, string $term, ?SearchConfig $config = null): void
    {
        $parsed = $this->parser->parse($term, $config);

        if ($parsed->isEmpty() || $columns === []) {
            return;
        }

        $strategy = SearchStrategies::for($query);

        $query->where(function (Builder $words) use ($parsed, $columns, $strategy): void {
            foreach ($parsed->tokens as $token) {
                // A comparison has no column type to be asked of here, so it is
                // looked for as the text that was typed.
                $pattern = $token->asText()->pattern;

                $words->where(function (Builder $anyColumn) use ($columns, $strategy, $pattern): void {
                    foreach ($columns as $column) {
                        $strategy->apply($anyColumn, new SearchClause(column: $column), $pattern);
                    }
                });
            }
        });
    }
}
