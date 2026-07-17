<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Support\Docs;

/**
 * Turns prose and code into comparable terms.
 *
 * Indexing and querying MUST share this class: the old index counted raw
 * substrings, so "form" scored a hit on "format" and "performance" and a query
 * for a fluent method drowned in incidental prose.
 *
 * Identifiers are emitted whole *and* split on camel-case boundaries, because
 * this corpus documents a fluent API: `TextColumn` yields "textcolumn", "text"
 * and "column", so a natural-language query ("text column") finds the class and
 * a copy-pasted identifier ("TextColumn") finds the prose describing it.
 */
class Tokenizer
{
    /**
     * Terms in occurrence order, duplicates intact — callers counting term
     * frequency need them.
     *
     * @return array<int, string>
     */
    public function tokenize(string $text): array
    {
        preg_match_all('/[A-Za-z0-9]+/', $text, $matches);

        $terms = [];

        foreach ($matches[0] as $word) {
            $whole = strtolower($word);

            if (strlen($whole) >= 2) {
                $terms[] = $whole;
            }

            foreach ($this->camelParts($word) as $part) {
                $piece = strtolower($part);

                if (strlen($piece) >= 2 && $piece !== $whole) {
                    $terms[] = $piece;
                }
            }
        }

        return $terms;
    }

    /**
     * Distinct terms — for queries, where repeating a word must not weight it.
     *
     * @return array<int, string>
     */
    public function unique(string $text): array
    {
        return array_values(array_unique($this->tokenize($text)));
    }

    /**
     * Split an identifier on camel-case humps: `TextColumn` => [Text, Column],
     * `HTMLParser` => [HTML, Parser].
     *
     * @return array<int, string>
     */
    private function camelParts(string $word): array
    {
        $parts = preg_split('/(?<=[a-z0-9])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][a-z])/', $word);

        return $parts === false || count($parts) < 2 ? [] : $parts;
    }
}
