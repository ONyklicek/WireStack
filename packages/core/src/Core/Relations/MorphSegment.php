<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

use NyonCode\WireCore\Core\Relations\Contracts\Segment;

/**
 * Segment for morph relations that require special handling (eager load, not join).
 */
final readonly class MorphSegment implements Segment
{
    public function __construct(
        public string $name,
        public ?string $morphType = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function isTerminal(): bool
    {
        return false;
    }
}
