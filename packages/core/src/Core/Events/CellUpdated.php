<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched after an inline edit saves.
 */
final readonly class CellUpdated
{
    public function __construct(
        public string $tableId,
        public string $column,
        public mixed $recordId,
        public mixed $oldValue,
        public mixed $newValue,
    ) {}
}
