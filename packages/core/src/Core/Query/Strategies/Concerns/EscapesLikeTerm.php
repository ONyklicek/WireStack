<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Strategies\Concerns;

/**
 * Shared LIKE/ILIKE term escaping for search strategies.
 *
 * The search term is bound as a parameter (no SQL injection), but its `%`, `_`
 * and `\` characters are LIKE metacharacters: an unescaped `%` matches anything
 * and lets a user turn a search into a full-table scan, while `_` matches any
 * single character. Each metacharacter is prefixed with a backslash, and the
 * query pairs the predicate with an explicit `ESCAPE '\'` so the behaviour is
 * identical across MySQL, PostgreSQL and SQLite (SQLite's LIKE has no default
 * escape character).
 */
trait EscapesLikeTerm
{
    /** The escape character paired with every LIKE/ILIKE predicate. */
    protected const LIKE_ESCAPE = '\\';

    /**
     * Escape LIKE metacharacters in a raw search term and wrap it as a
     * "contains" pattern (`%term%`).
     */
    protected function likeContains(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
