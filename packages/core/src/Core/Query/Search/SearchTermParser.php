<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * Turns what the user typed into the tokens the query is built from.
 *
 * The parser is the only place that reads {@see SearchConfig}: it resolves the
 * configuration into finished tokens (including each token's LIKE pattern), so
 * the pipe, the compiler and the driver strategies never branch on it.
 *
 * It is deliberately conservative. A chunk only becomes a comparison when both
 * the operator and the value are unambiguous — `>100`, `10..20`, `2026-01-01..`
 * — and anything else stays the literal text it appears to be, so an ordinary
 * search can never be reinterpreted into something the user did not ask for.
 */
final class SearchTermParser
{
    /** Operators recognised at the head of a token, longest first. */
    private const COMPARISON_PATTERN = '/^(>=|<=|>|<|=)\s*(.+)$/u';

    /** A range: `10..20`, `10..` (from), `..20` (up to). */
    private const RANGE_PATTERN = '/^(.*?)\.\.(.*)$/u';

    /** An ISO-ish date: `2026`, `2026-01`, `2026-01-31`. */
    private const ISO_DATE_PATTERN = '/^\d{4}(-\d{1,2}){0,2}$/';

    /** A day-first date, as written across most of Europe: `31.01.2026`, `31/01/2026`. */
    private const EURO_DATE_PATTERN = '/^\d{1,2}[.\/]\d{1,2}[.\/]\d{4}$/';

    public function parse(string $term, ?SearchConfig $config = null): SearchTerm
    {
        $config ??= SearchConfig::make();
        $trimmed = trim($term);

        if ($trimmed === '') {
            return SearchTerm::empty();
        }

        $tokens = [];

        foreach ($this->chunk($trimmed, $config) as [$raw, $isPhrase]) {
            $token = $this->toToken($raw, $isPhrase, $config);

            if ($token === null) {
                continue;
            }

            $tokens[] = $token->withPrefix($this->prefixFor($token, $tokens));
        }

        if ($tokens === []) {
            return SearchTerm::empty();
        }

        return new SearchTerm(tokens: $tokens, raw: $trimmed);
    }

    /**
     * The series a range was typed inside, if it was typed inside one.
     *
     * `8866 01..08` is one thought split into two tokens by the space in the
     * code itself. The preceding *word* — never a phrase, never another
     * comparison — is carried along so that a column holding such a code can
     * put the two halves back together. Which of the two readings applies is
     * decided per column, not here.
     *
     * @param  array<int, SearchToken>  $preceding
     */
    private function prefixFor(SearchToken $token, array $preceding): ?string
    {
        if (! $token->isComparison() || $preceding === []) {
            return null;
        }

        $previous = $preceding[count($preceding) - 1];

        if ($previous->isComparison() || $previous->isPhrase) {
            return null;
        }

        return $previous->value;
    }

    /**
     * Split the term into raw chunks, each flagged as quoted or not.
     *
     * Without tokenization there is exactly one chunk — the whole term — which
     * keeps a literal search byte-identical to what it was before any of this
     * existed.
     *
     * @return array<int, array{0: string, 1: bool}>
     */
    private function chunk(string $term, SearchConfig $config): array
    {
        if (! $config->tokenizes()) {
            return [[$term, false]];
        }

        // Either a double-quoted run (kept whole, quotes dropped) or a bare run
        // of non-space characters.
        preg_match_all('/"([^"]*)"|(\S+)/u', $term, $matches, PREG_SET_ORDER);

        $chunks = [];

        foreach ($matches as $match) {
            $isPhrase = str_starts_with($match[0], '"');
            $value = $isPhrase ? $match[1] : $match[0];

            if (trim($value) === '') {
                continue;
            }

            $chunks[] = [$value, $isPhrase];
        }

        return $chunks;
    }

    /**
     * Interpret one chunk.
     */
    private function toToken(string $raw, bool $isPhrase, SearchConfig $config): ?SearchToken
    {
        if ($raw === '') {
            return null;
        }

        $pattern = LikePattern::contains($raw, $config->allowsWildcards());

        // Quoting is how a user says "take this literally", so a phrase is never
        // read as an operator.
        if ($isPhrase || ! $config->parsesRanges()) {
            return SearchToken::contains($raw, $pattern, $isPhrase);
        }

        return $this->toRangeToken($raw, $pattern)
            ?? $this->toComparisonToken($raw, $pattern)
            ?? SearchToken::contains($raw, $pattern);
    }

    /**
     * `10..20`, `10..`, `..20` — checked before the single-sided operators so
     * that the dots win over a leading `>` that is not there.
     */
    private function toRangeToken(string $raw, string $pattern): ?SearchToken
    {
        if (preg_match(self::RANGE_PATTERN, $raw, $match) !== 1) {
            return null;
        }

        $lower = trim($match[1]);
        $upper = trim($match[2]);

        $hasLower = $lower !== '' && $this->isComparable($lower);
        $hasUpper = $upper !== '' && $this->isComparable($upper);

        // A side that is present but not comparable (`foo..bar`) means this was
        // never a range — it is text that happens to contain two dots.
        if (($lower !== '' && ! $hasLower) || ($upper !== '' && ! $hasUpper)) {
            return null;
        }

        return match (true) {
            $hasLower && $hasUpper => new SearchToken(
                operator: SearchOperator::Between,
                value: $lower,
                upper: $upper,
                raw: $raw,
                pattern: $pattern,
            ),
            $hasLower => new SearchToken(
                operator: SearchOperator::GreaterThanOrEqual,
                value: $lower,
                upper: null,
                raw: $raw,
                pattern: $pattern,
            ),
            $hasUpper => new SearchToken(
                operator: SearchOperator::LessThanOrEqual,
                value: $upper,
                upper: null,
                raw: $raw,
                pattern: $pattern,
            ),
            default => null,
        };
    }

    /**
     * `>100`, `>=100`, `<10`, `<=10`, `=42`.
     */
    private function toComparisonToken(string $raw, string $pattern): ?SearchToken
    {
        if (preg_match(self::COMPARISON_PATTERN, $raw, $match) !== 1) {
            return null;
        }

        $value = trim($match[2]);

        if (! $this->isComparable($value)) {
            return null;
        }

        return new SearchToken(
            // The pattern only ever captures an operator this enum declares.
            operator: SearchOperator::from($match[1]),
            value: $value,
            upper: null,
            raw: $raw,
            pattern: $pattern,
        );
    }

    /**
     * Whether a value can stand on the right of a comparison.
     *
     * Numbers and dates only — a comparison against arbitrary text would
     * compare lexically, which is never what the user meant.
     */
    private function isComparable(string $value): bool
    {
        return is_numeric($value) || $this->looksLikeDate($value);
    }

    private function looksLikeDate(string $value): bool
    {
        return preg_match(self::ISO_DATE_PATTERN, $value) === 1
            || preg_match(self::EURO_DATE_PATTERN, $value) === 1;
    }
}
