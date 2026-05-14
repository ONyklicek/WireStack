<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched before filters are applied to the table query.
 */
final readonly class TableFiltering
{
    public function __construct(
        public string $tableId,
        /** @var array<string, mixed> */
        public array $filters,
    ) {}
}
