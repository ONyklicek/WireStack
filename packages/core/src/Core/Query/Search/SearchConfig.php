<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

/**
 * How a search box interprets what is typed into it.
 *
 * Splitting on spaces is on by default: `novak praha` finds the row whose name
 * is in one column and city in another, in any order, which is what every
 * search box in the stack is expected to do — the palette, a select, a table.
 * A box that must match the whole term as one substring says `literal()`.
 *
 * Ranges and wildcards stay opt-in, because both read meaning into characters
 * an ordinary search may contain.
 *
 * Escaping is not on this object. A `%` typed by a user being a wildcard was a
 * bug, not a feature, and is fixed unconditionally.
 */
final class SearchConfig
{
    protected bool $tokenize = true;

    protected bool $ranges = false;

    protected bool $wildcards = false;

    public static function make(): self
    {
        return new self;
    }

    /**
     * Treat spaces as AND: every word must match, each across all columns.
     *
     * `Ada Lovelace` then finds the row whose first name is in one column and
     * surname in another. Double quotes keep a phrase together.
     */
    public function tokenize(bool $enabled = true): static
    {
        $this->tokenize = $enabled;

        return $this;
    }

    /**
     * Understand comparisons and ranges: `>100`, `<=20`, `10..20`, `2026-01-01..`.
     *
     * Applied only to columns that hold a number or a date; a comparison no
     * column can answer is searched as literal text instead.
     */
    public function ranges(bool $enabled = true): static
    {
        $this->ranges = $enabled;

        return $this;
    }

    /**
     * Let the user write `*` and `?` as wildcards (`nov*` matches `novak`).
     *
     * The real LIKE metacharacters stay escaped either way.
     */
    public function wildcards(bool $enabled = true): static
    {
        $this->wildcards = $enabled;

        return $this;
    }

    /**
     * Match the term exactly as typed — no splitting, no operators, no wildcards.
     */
    public function literal(): static
    {
        $this->tokenize = false;
        $this->ranges = false;
        $this->wildcards = false;

        return $this;
    }

    public function tokenizes(): bool
    {
        return $this->tokenize;
    }

    public function parsesRanges(): bool
    {
        return $this->ranges;
    }

    public function allowsWildcards(): bool
    {
        return $this->wildcards;
    }
}
