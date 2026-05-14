<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched after search completes.
 */
final readonly class TableSearched
{
    public function __construct(
        public string $tableId,
        public string $term,
        public int $resultsCount,
    ) {}
}
