<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

use NyonCode\WireCore\Core\Relations\Contracts\Segment;

/**
 * Terminal segment representing a column/attribute (e.g., "email" in "author.email").
 */
final readonly class ColumnSegment implements Segment
{
    public function __construct(
        public string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
