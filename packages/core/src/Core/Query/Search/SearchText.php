<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

use Illuminate\Support\Str;

/**
 * Text matching for a search answered in PHP rather than in SQL.
 *
 * A database decides case and accents by its collation — `utf8mb4_unicode_ci`
 * finds `Novák` for `novak` without being asked. A search answered over an
 * array has no collation, so it states the same rule here: both sides are
 * folded to lower-case ASCII before they are compared, and `novak` finds
 * `Novák`, `cerny` finds `Černý`.
 *
 * The browser has the twin of this (`core/resources/js/support/search.js`), so a
 * select filtering its options client-side and a table filtering an array agree
 * on what matches.
 */
final class SearchText
{
    /**
     * Lower-case ASCII, which is what both sides of a comparison are reduced to.
     */
    public static function fold(string $text): string
    {
        return mb_strtolower(Str::ascii($text));
    }

    /**
     * Whether the haystack contains the needle, ignoring case and accents.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains(self::fold($haystack), self::fold($needle));
    }

    /**
     * Whether every token of the term is found in at least one of the texts.
     *
     * Each token may sit in a different text — the first name in one column,
     * the surname in another — which is the AND-of-ORs the SQL path builds.
     * A comparison token is matched as the text the user typed, since a plain
     * string cannot answer `>100`.
     *
     * @param  array<int, string>  $texts
     */
    public static function matches(array $texts, SearchTerm $term): bool
    {
        $folded = array_map(self::fold(...), $texts);

        foreach ($term->tokens as $token) {
            $needle = self::fold($token->searchText());

            $found = array_filter($folded, static fn (string $text): bool => str_contains($text, $needle)) !== [];

            if (! $found) {
                return false;
            }
        }

        return true;
    }
}
