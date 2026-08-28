<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Component;
use NyonCode\WireTable\Support\GroupPartitions;
use NyonCode\WireTable\Table;

/**
 * The host's half of grouping: the ordering that keeps groups together, and the
 * per-group subtotals under them.
 *
 * `Table` owns the declaration — {@see Table::groupBy()}, the labels, the
 * comparison key — through `Concerns\HasGrouping`. What is left is the part that
 * needs the page: a group is a run of rows on the current page, so a group that
 * crosses a page boundary subtotals per page, and the subtotals are computed in
 * memory over rows already fetched rather than by re-querying per group.
 *
 * Two things make that correct rather than merely fast:
 *
 * 1. **The rows have to arrive grouped.** {@see applyGroupOrdering()} prepends
 *    an order on the group column so every other sort applies *within* a group.
 *    It stands aside when the viewer sorts by the group column themselves —
 *    that sort already keeps groups contiguous, and prepending a second one
 *    would override the direction they asked for.
 * 2. **The split has to belong to the page it describes.** The page is split
 *    once per render into {@see GroupPartitions}, which carries the identity of
 *    the record set it split. Paging inside one request therefore cannot leave
 *    subtotals describing the page before — which it did, for as long as the
 *    memo was invalidated by hand at five call sites and two of them forgot.
 *
 * Only `query`- and `page`-scoped summaries subtotal here. A `selection` or
 * `subRows` scope does not describe a group.
 *
 * @phpstan-require-extends Component
 */
trait CanGroupRecords
{
    /**
     * The current page split by group value — see {@see GroupPartitions}, which
     * knows which page it describes, so this needs no hand invalidation.
     */
    protected ?GroupPartitions $groupPartitions = null;

    /**
     * Keep groups contiguous: prepend an order on the group column so every
     * other sort applies within a group. Skipped when the user explicitly
     * sorts by the group column — that sort already keeps groups together.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyGroupOrdering(Builder $query): Builder
    {
        $table = $this->getTable();
        $groupColumn = $table->getGroupColumn();

        if ($groupColumn === null) {
            return $query;
        }

        if ($this->tableState->get('sort.column', '') === $groupColumn) {
            return $query;
        }

        $base = $query->getQuery();
        $base->orders = array_merge(
            [['column' => $query->qualifyColumn($groupColumn), 'direction' => 'asc']],
            $base->orders ?? [],
        );

        return $query;
    }

    /**
     * Whether group subtotal rows should render: grouping is active, enabled,
     * and at least one column has a summary to subtotal.
     */
    public function tableHasGroupSummaries(): bool
    {
        $table = $this->getTable();

        if (! $table->hasGrouping() || ! $table->hasGroupSummaries()) {
            return false;
        }

        foreach ($table->getColumns() as $column) {
            if ($column->hasSummary()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Per-group subtotals, computed in memory over the group's records on the
     * current page (groups crossing a page boundary subtotal per page).
     *
     * @return array<string, array<int, array<string, mixed>>> [columnName => [['label' => …, 'value' => …], …]]
     */
    public function computeGroupSummaries(mixed $groupValue): array
    {
        $table = $this->getTable();

        if (! $table->hasGrouping()) {
            return [];
        }

        $groupRecords = $this->getGroupRecords($groupValue);

        $summaries = [];

        foreach ($table->getColumns() as $column) {
            if (! $column->hasSummary()) {
                continue;
            }

            // In-memory over the group's rows; selection/subRows scopes don't
            // describe a group, so only query/page declarations subtotal.
            $summaries[$column->getName()] = $column->computeSummaries(
                $groupRecords,
                null,
                ['query', 'page'],
            );
        }

        return $summaries;
    }

    /**
     * Records of one group on the current page.
     *
     * The page is split once per render rather than filtered per group: the
     * subtotals below a table with G groups would otherwise cost G passes over
     * the whole page for an answer that cannot change between them.
     *
     * @return Collection<int, Model>
     */
    protected function getGroupRecords(mixed $groupValue): Collection
    {
        return $this->tableGroupPartitions()->get($groupValue);
    }

    /**
     * The current page, split by group value.
     *
     * Rebuilt when — and only when — the page memo hands back a different record
     * set than the one that was split. That is the whole invalidation rule, and
     * it lives here rather than at every call site that drops the page memo:
     * `setPage()` and `setTableCursor()` used to drop the records and keep the
     * partitions, so a same-request page change left the group on screen
     * subtotalling zero while a group that had scrolled away kept its figure.
     */
    protected function tableGroupPartitions(): GroupPartitions
    {
        $records = $this->getTableRecords();

        if ($this->groupPartitions?->describes($records) === true) {
            return $this->groupPartitions;
        }

        $table = $this->getTable();
        $page = $records instanceof Collection ? $records : collect($records->items());

        return $this->groupPartitions = GroupPartitions::of(
            $page,
            // Normalised, never the raw attribute: a cast column hands back a
            // fresh object per record and every row would form its own group.
            fn (Model $record): mixed => $table->getGroupComparisonKey($record),
            $records,
        );
    }
}
