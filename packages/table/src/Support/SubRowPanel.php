<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Contracts\ExpandsTableRows;
use NyonCode\WireTable\Contracts\SummarisesTable;
use NyonCode\WireTable\Services\SubRowFilters;
use NyonCode\WireTable\Table;

/**
 * One expanded parent's child panel, as numbers rather than as markup.
 *
 * The panel is rendered **twice** — the desktop `<table>` inside a spanning row,
 * and the list a stacked card shows on a phone — and both renderings need the
 * same handful of derived values: how many children exist in total, how many the
 * limit is still hiding, and whether there are subtotals to draw. Both used to
 * derive them inside their own `@php` block, from the same four lines copied
 * across, which is the shape {@see GroupPartitions} was extracted out of for
 * the same reason.
 *
 * The desktop half also carried the rule for *"is a sub-row filter active"* —
 * and its copy was the **correct** one. The canonical owner,
 * {@see SubRowFilters::hasActiveInteractive()}, still answered the pre-seed
 * version of the question, so a table with one multi-select sub-row column read
 * as permanently filtered and lost the page-wide eager load. Nothing saw it, because the only copy that mattered to
 * the eye was the one in Blade, and Blade is where nobody asserts. Both now ask
 * the service.
 *
 * Everything here is decided once per open parent. `total` can cost a COUNT
 * (that is `getSubRowsTotalCount()`'s contract, and only when a `subRowsLimit`
 * is set and this parent has not been expanded past it), which is exactly what
 * the two views already paid — the number is now paid for once and read twice.
 */
final readonly class SubRowPanel
{
    /**
     * @param  array<int, Column>  $columns  The child columns this viewer may see.
     * @param  array<string, mixed>  $filterValues  The `rows.subRowFilters` slots.
     * @param  array<string, array<int, mixed>>  $summaries  Per-column subtotal entries.
     */
    private function __construct(
        public array $columns,
        public bool $hasFilterBar,
        public bool $hasActiveFilter,
        public array $filterValues,
        public bool $hasActions,
        public int $total,
        public int $remaining,
        public int $columnCount,
        public array $summaries,
        public int $summaryRowCount,
        public bool $showsSummaries,
    ) {}

    /**
     * @param  object  $host  The Livewire component. Typed loosely for the same
     *                        reason {@see ColumnRenderPlan::resolve()} types its own — a consumer's
     *                        component needs no `implements` to keep working. What it must be able
     *                        to answer is {@see ExpandsTableRows} plus {@see SummarisesTable}, and
     *                        that is what a test double implements.
     * @param  Collection<int, Model>  $subRows  The children actually being rendered,
     *                                           already limited and filtered.
     * @param  array<int, Column>|null  $columns  The plan's resolved list, when the caller
     *                                            has one; resolved from the table otherwise, which is what a consumer's
     *                                            own `subRowView` include gets.
     */
    public static function for(
        Table $table,
        object $host,
        Model $record,
        string|int $recordKey,
        Collection $subRows,
        ?array $columns = null,
    ): self {
        $columns = $columns !== null ? array_values($columns) : $table->getViewableSubRowColumns();

        $filterValues = $host->getSubRowFilterValues();

        // The bar renders per *configured* column, not per visible one: a column
        // hidden from this viewer takes its filter control with it, but it must
        // not decide whether the bar exists at all.
        $hasFilterBar = $table->isSubRowsFilterable() && count($table->getSubRowColumns()) > 0;

        $showAll = $host->isSubRowsShowAll($recordKey);
        $limit = $table->getSubRowsLimit();

        // Without a limit the rendered set *is* the whole set, so asking for a
        // total would be a query bought to learn what is already in hand.
        $total = ($limit && ! $showAll) ? $host->getSubRowsTotalCount($record) : $subRows->count();

        $summaries = $host->computeTableSummaries('subRows', $record, $subRows);
        $hasActions = $table->hasSubRowActions();

        return new self(
            columns: $columns,
            hasFilterBar: $hasFilterBar,
            hasActiveFilter: $host->hasActiveSubRowFilters(),
            filterValues: $filterValues,
            hasActions: $hasActions,
            total: $total,
            // A negative remainder is not a rounding artefact but a real state:
            // "show all" hands back more rows than the total taken before it,
            // and a limit raised between renders does the same.
            remaining: max(0, $total - $subRows->count()),
            // The indent spacer is a column too, and so is the overflow cell —
            // a colspan that forgets either leaves the empty-state message and
            // the subtotal row short of the edge they are meant to span.
            columnCount: count($columns) + 1 + ($hasActions ? 1 : 0),
            summaries: $summaries,
            summaryRowCount: self::summaryRowCount($summaries),
            // Subtotals under nothing are a footer describing an empty set; the
            // "no children" message already says it, and says it once.
            showsSummaries: $summaries !== [] && $subRows->isNotEmpty(),
        );
    }

    /**
     * @param  array<string, array<int, mixed>>  $summaries
     */
    private static function summaryRowCount(array $summaries): int
    {
        $rows = 0;

        foreach ($summaries as $entries) {
            $rows = max($rows, count($entries));
        }

        return $rows;
    }
}
