<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched before an inline edit saves.
 */
final readonly class CellUpdating
{
    public function __construct(
        public string $tableId,
        public string $column,
        public mixed $recordId,
        public mixed $value,
    ) {}
}
