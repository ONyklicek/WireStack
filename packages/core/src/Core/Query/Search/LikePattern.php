<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * Builds the LIKE/ILIKE pattern a search term is matched with.
 *
 * The term is always bound as a parameter, so this is not about injection — it
 * is about `%` and `_`, which are LIKE metacharacters. Left raw, a user typing
 * `50%` matches every row and turns the search into a full table scan, and `_`
 * silently matches any character.
 *
 * ## Why the escape character is `!` and not `\`
 *
 * An earlier attempt escaped with a backslash and paired the predicate with
 * `ESCAPE '\'`. SQLite and PostgreSQL accept that, which is why the test suite
 * stayed green — but in MySQL/MariaDB the backslash inside a string literal
 * escapes the closing quote, so `ESCAPE '\'` is a syntax error and every search
 * on those engines died. That work was reverted wholesale.
 *
 * `!` has no special meaning inside a string literal on any supported engine,
 * so one pattern shape works everywhere and there is nothing to special-case
 * per driver. The clause is always declared explicitly because SQLite's LIKE
 * has no default escape character at all.
 */
final class LikePattern
{
    /** The escape character declared by every LIKE/ILIKE predicate. */
    public const ESCAPE = '!';

    /**
     * Wrap a term as a "contains" pattern with its metacharacters escaped.
     *
     * With `$wildcards` on, the user's own `*` and `?` survive escaping and are
     * translated to `%` and `_` afterwards, so they act as wildcards while a
     * literal `%` typed by the same user still does not.
     */
    public static function contains(string $term, bool $wildcards = false): string
    {
        return '%'.self::escape($term, $wildcards).'%';
    }

    /**
     * Escape the LIKE metacharacters in a raw term.
     */
    public static function escape(string $term, bool $wildcards = false): string
    {
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term,
        );

        if (! $wildcards) {
            return $escaped;
        }

        // `*` and `?` were never metacharacters, so they passed through the
        // escaping above untouched and can now become the real ones.
        return strtr($escaped, ['*' => '%', '?' => '_']);
    }

    /**
     * The `ESCAPE '…'` suffix every LIKE predicate is paired with.
     */
    public static function escapeClause(): string
    {
        return " ESCAPE '".self::ESCAPE."'";
    }
}
