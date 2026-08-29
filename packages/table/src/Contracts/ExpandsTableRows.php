<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

use Illuminate\Support\Collection;
use NyonCode\WireTable\Services\SubRowFilters;
use NyonCode\WireTable\Support\SubRowPanel;

/**
 * A host that knows which rows are expanded, and what is inside them.
 *
 * Separate from {@see ShowsTableColumns} rather than folded in with it: a table
 * without sub-rows answers the column question and has no expansion state at
 * all, and an interface that demanded both would make the renderers ask for
 * something half its hosts do not have.
 *
 * Wider than the one predicate it started as, for the reason
 * {@see SummarisesTable} gives for its own seven: these are one capability
 * asked at different depths — which rows are open, how far each is opened, what
 * is in them and what the filter bar above them holds. A host that can answer
 * any of them can answer all of them, and splitting them would leave
 * {@see SubRowPanel} asking four interfaces about one panel.
 */
interface ExpandsTableRows
{
    public function isRowExpanded(mixed $recordKey): bool;

    /**
     * This parent's children, already filtered, sorted and limited.
     *
     * @return Collection<int, mixed>
     */
    public function getSubRows(mixed $record): Collection;

    /** Whether this parent has been expanded past the configured `subRowsLimit`. */
    public function isSubRowsShowAll(string|int $parentKey): bool;

    /** Every child of this parent, honouring sub-row filters — what "show N more" counts against. */
    public function getSubRowsTotalCount(mixed $record): int;

    /** The child count already in memory, or null when only a query could answer. */
    public function getLoadedSubRowCount(mixed $record): ?int;

    /**
     * The interactive sub-row filter bar's raw slots.
     *
     * Every slot is seeded at mount, so an untouched bar is a full array of
     * empty values rather than an empty array — see {@see SubRowFilters}.
     *
     * @return array<string, mixed>
     */
    public function getSubRowFilterValues(): array;

    /** Whether any slot above holds a value the user actually chose. */
    public function hasActiveSubRowFilters(): bool;
}
