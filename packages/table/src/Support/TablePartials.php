<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Table;

/**
 * Everything a set of changed rows moves, as a map of partial name to markup.
 *
 * A write to one cell does not only move that row. It moves the row's card on
 * the width where the table is hidden, the group subtotal the row belongs to,
 * and the grand totals — each of which lives outside the row and has to be
 * re-rendered on its own or go stale while the row beside it updates.
 *
 * Deciding *which* of those a change touches is a question about the table, not
 * about Livewire, and this answers it. Handing the answers back as a map rather
 * than queueing them is what keeps it that way: the host loops the map through
 * its own `renderPartial()`, and everything up to that point can be exercised
 * without a browser — which is where this logic used to be reachable only.
 *
 * The markup is closures, so a partial the host decides not to send costs
 * nothing to have been offered.
 */
final class TablePartials
{
    private function __construct(
        private readonly Table $table,
        private readonly object $host,
        private readonly TableRenderPlan $plan,
    ) {}

    public static function for(Table $table, object $host, TableRenderPlan $plan): self
    {
        return new self($table, $host, $plan);
    }

    /**
     * The rows themselves, keyed `row-{key}`.
     *
     * Position matters and travels with the record: a row renders differently
     * depending on where it sits — striping, and the first/last row's chrome —
     * so it is the position on the page, not the position among the changed.
     *
     * @param  array<array-key, mixed>  $page  Every record on the page, in order.
     * @param  array<int, array-key>  $changed  Keys whose contents moved.
     * @return array<string, callable(): string>
     */
    public function rows(array $page, array $changed): array
    {
        $rows = RowRenderer::for($this->table, $this->host, $this->plan);
        $partials = [];
        $position = 0;

        foreach ($page as $recordKey => $record) {
            $index = $position++;

            if (! in_array($recordKey, $changed, true)) {
                continue;
            }

            $partials['row-'.$recordKey] = fn (): string => $rows->render($record, $index);
        }

        return $partials;
    }

    /**
     * Everything else the changed records move: their cards, their groups'
     * subtotals, and the table's totals.
     *
     * @param  array<array-key, mixed>  $records  The changed records only.
     * @return array<string, callable(): string>
     */
    public function satellites(array $records): array
    {
        if ($records === []) {
            return [];
        }

        $partials = [];
        $stacked = $this->table->isStackedOnMobile();

        if ($stacked) {
            $cards = CardRenderer::for($this->table, $this->host, $this->plan);

            foreach ($records as $recordKey => $record) {
                $partials['card-'.$recordKey] = fn (): string => $cards->render($record);
            }
        }

        $summaries = SummaryRenderer::for($this->table, $this->host, $this->plan);

        // A group's subtotal is moved by a write to any of its members, and it
        // is a sibling row rather than part of one — so each changed record's
        // group is re-rendered, once however many of its rows moved.
        if ($this->host->tableHasGroupSummaries()) {
            foreach ($this->changedGroups($records) as $groupValue) {
                foreach ($summaries->group($groupValue) as $name => $html) {
                    $partials[$name] = fn (): string => $html;
                }
            }
        }

        if (! $this->host->tableHasSummaries()) {
            return $partials;
        }

        $partials['summary'] = fn (): string => $summaries->desktop();

        if ($stacked) {
            $partials['summary-mobile'] = fn (): string => $summaries->mobile();
        }

        return $partials;
    }

    /**
     * The distinct groups the changed records belong to.
     *
     * Keyed by the string form so two records of one group collapse to one
     * subtotal render, while the value keeps its original type — the renderer
     * matches groups by that, not by its string.
     *
     * @param  array<array-key, mixed>  $records
     * @return array<string, mixed>
     */
    private function changedGroups(array $records): array
    {
        $groups = [];

        foreach ($records as $record) {
            $value = $this->table->getGroupComparisonKey($record);
            $groups[(string) $value] ??= $value;
        }

        return $groups;
    }
}
