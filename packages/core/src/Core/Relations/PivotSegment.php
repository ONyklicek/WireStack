<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

use NyonCode\WireCore\Core\Relations\Contracts\Segment;

/**
 * Segment for pivot table attributes (e.g., "pivot.created_at").
 */
final readonly class PivotSegment implements Segment
{
    public function __construct(
        public string $attribute,
    ) {}

    public function getName(): string
    {
        return "pivot.{$this->attribute}";
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
