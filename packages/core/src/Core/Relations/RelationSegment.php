<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations;

use NyonCode\WireCore\Core\Relations\Contracts\Segment;

/**
 * A relation traversal segment (e.g., "posts" in "posts.comments.author.email").
 */
final readonly class RelationSegment implements Segment
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
        return false;
    }
}
