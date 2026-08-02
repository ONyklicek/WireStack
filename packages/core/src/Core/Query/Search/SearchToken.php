<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * One indivisible piece of what the user typed.
 *
 * A literal search is a single token; a tokenized one is several, and every
 * token must match for a row to survive (they AND together, while the columns
 * inside a token OR — see the pipe that applies them).
 *
 * The token carries its own LIKE pattern rather than a flag saying how to build
 * one, so the parser stays the only place that knows the search configuration
 * and everything downstream is configuration-free.
 */
final readonly class SearchToken
{
    /**
     * @param  SearchOperator  $operator  What this token asks of a column
     * @param  string  $value  The compared value, or the text to match
     * @param  string|null  $upper  The upper bound of a `Between`
     * @param  string  $raw  Exactly what the user typed for this token
     * @param  string  $pattern  The LIKE pattern matching this token as text
     * @param  bool  $isPhrase  Whether the user quoted it (never operator-parsed)
     * @param  string|null  $prefix  The word typed directly before this range
     */
    public function __construct(
        public SearchOperator $operator,
        public string $value,
        public ?string $upper,
        public string $raw,
        public string $pattern,
        public bool $isPhrase = false,
        public ?string $prefix = null,
    ) {}

    /**
     * A plain substring token.
     */
    public static function contains(string $value, string $pattern, bool $isPhrase = false, ?string $raw = null): self
    {
        return new self(
            operator: SearchOperator::Contains,
            value: $value,
            upper: null,
            raw: $raw ?? $value,
            pattern: $pattern,
            isPhrase: $isPhrase,
        );
    }

    public function isComparison(): bool
    {
        return $this->operator->isComparison();
    }

    /**
     * The same token carrying the word typed directly before it.
     *
     * `8866 01..08` splits into the word `8866` and the range `01..08`, and a
     * structured code needs both halves to mean anything: only a column that
     * holds such a code reads the word as the series the range runs inside.
     * Every other column ignores it and compares `01..08` as it stands, so the
     * two readings coexist instead of one having to win at parse time.
     */
    public function withPrefix(?string $prefix): self
    {
        return new self(
            operator: $this->operator,
            value: $this->value,
            upper: $this->upper,
            raw: $this->raw,
            pattern: $this->pattern,
            isPhrase: $this->isPhrase,
            prefix: $prefix,
        );
    }

    /**
     * A bound completed with the series the range was typed inside.
     *
     * The word and the range were separated by whitespace, which the parser has
     * already collapsed, so they rejoin with exactly one space.
     */
    public function qualify(string $bound): string
    {
        return $this->prefix === null ? $bound : $this->prefix.' '.$bound;
    }

    /**
     * The same token demoted to a plain substring match.
     *
     * A comparison no column can answer — `>100` on a table of names — is
     * searched as the literal text the user typed instead of silently matching
     * nothing, so what is on screen always explains itself.
     */
    public function asText(): self
    {
        if (! $this->isComparison()) {
            return $this;
        }

        return self::contains($this->raw, $this->pattern, $this->isPhrase, $this->raw);
    }

    /**
     * The text a host-supplied search callback should look for.
     */
    public function searchText(): string
    {
        return $this->isComparison() ? $this->raw : $this->value;
    }
}
