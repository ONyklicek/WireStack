<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Services\SummaryFormatter;

/**
 * How a column wants its summary values rendered.
 *
 * Everything {@see SummaryFormatter} needs to know
 * about a column, and nothing more — so the formatter never reaches back into
 * one. Immutable, per the coding standard's preference for value objects over
 * shared mutable state.
 */
final readonly class SummaryFormat
{
    public function __construct(
        public ?int $decimals = null,
        public string $decimalSeparator = '.',
        public string $thousandsSeparator = ' ',
        public ?string $prefix = null,
        public ?string $suffix = null,
    ) {}

    /** Whether a prefix or suffix would decorate a formatted number. */
    public function hasDecorations(): bool
    {
        return $this->prefix !== null || $this->suffix !== null;
    }
}
