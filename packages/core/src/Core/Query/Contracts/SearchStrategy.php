<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\Search\LikePattern;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * Database-specific text matching for a search clause.
 *
 * The only thing that actually differs between engines is the operator (MySQL
 * and SQLite say LIKE, PostgreSQL says ILIKE to match case-insensitively), so
 * that is all a strategy owns. Splitting the term, reading operators out of it,
 * and comparing numbers and dates are engine-independent and happen upstream.
 */
interface SearchStrategy
{
    /**
     * Match a prepared pattern against the clause's column, OR-ed into the
     * group the caller has opened.
     *
     * The pattern arrives finished — already wrapped in `%…%` and with its LIKE
     * metacharacters escaped by {@see LikePattern},
     * whose escape character every implementation must declare on the predicate.
     *
     * @param  Builder<Model>  $builder
     * @param  string  $pattern  A LIKE pattern, not a raw search term
     */
    public function apply(Builder $builder, SearchClause $clause, string $pattern): void;
}
