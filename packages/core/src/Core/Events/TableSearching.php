<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Events;

/**
 * Dispatched before search is applied to the table query.
 */
final readonly class TableSearching
{
    public function __construct(
        public string $tableId,
        public string $term,
        /** @var array<int, string> */
        public array $searchableColumns = [],
    ) {}
}
