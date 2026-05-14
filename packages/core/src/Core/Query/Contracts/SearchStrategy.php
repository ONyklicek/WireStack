<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\SearchClause;

/**
 * Database-specific search strategy.
 *
 * Different databases support different search mechanisms:
 * - MySQL: LIKE, FULLTEXT
 * - PostgreSQL: ILIKE, tsvector
 * - SQLite: LIKE (case-insensitive by default)
 */
interface SearchStrategy
{
    /**
     * Apply a search clause against the builder.
     *
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, SearchClause $clause, string $term): void;
}
