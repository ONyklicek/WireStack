<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched after table data is refreshed/reloaded.
 */
final readonly class TableRefreshed
{
    public function __construct(
        public string $tableId,
        public ?int $recordCount = null,
        public ?float $queryTimeMs = null,
    ) {}
}
