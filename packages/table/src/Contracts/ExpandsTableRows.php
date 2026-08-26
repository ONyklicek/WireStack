<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

/**
 * A host that knows which rows are expanded.
 *
 * Separate from {@see ShowsTableColumns} rather than folded in with it: a table
 * without sub-rows answers the column question and has no expansion state at
 * all, and an interface that demanded both would make the renderers ask for
 * something half its hosts do not have.
 */
interface ExpandsTableRows
{
    public function isRowExpanded(mixed $recordKey): bool;
}
