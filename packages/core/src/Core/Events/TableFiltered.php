<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched after filters are applied to the table query.
 */
final readonly class TableFiltered
{
    public function __construct(
        public string $tableId,
        /** @var array<string, mixed> */
        public array $filters,
        public int $resultsCount,
    ) {}
}
