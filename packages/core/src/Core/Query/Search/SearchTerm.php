<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * A parsed search term: what the user typed, split into tokens.
 *
 * Every token must match (AND); within one token the searchable columns OR.
 */
final readonly class SearchTerm
{
    /**
     * @param  array<int, SearchToken>  $tokens
     * @param  string  $raw  The unparsed term, as typed
     */
    public function __construct(
        public array $tokens,
        public string $raw = '',
    ) {}

    /**
     * The whole term as one substring match — the behaviour of a table that has
     * not opted into any search parsing, and the shape a bare string argument
     * is promoted to.
     */
    public static function literal(string $term): self
    {
        if ($term === '') {
            return self::empty();
        }

        return new self(
            tokens: [SearchToken::contains($term, LikePattern::contains($term))],
            raw: $term,
        );
    }

    public static function empty(): self
    {
        return new self(tokens: [], raw: '');
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }
}
