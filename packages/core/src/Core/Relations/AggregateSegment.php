<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

use NyonCode\WireCore\Core\Relations\Contracts\Segment;

/**
 * Terminal segment for aggregate operations (e.g., count, sum, avg).
 *
 * Usage: "orders->count()" or "orders->sum(total)"
 */
final readonly class AggregateSegment implements Segment
{
    public function __construct(
        public string $relation,
        public string $function,
        public ?string $column = null,
    ) {}

    public function getName(): string
    {
        $name = "{$this->relation}_{$this->function}";
        if ($this->column !== null) {
            $name .= "_{$this->column}";
        }

        return $name;
    }

    public function isTerminal(): bool
    {
        return true;
    }
}
