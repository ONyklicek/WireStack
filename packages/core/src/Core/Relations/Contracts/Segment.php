<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Relations\Contracts;

/**
 * A single segment in a relation path.
 */
interface Segment
{
    public function getName(): string;

    public function isTerminal(): bool;
}
