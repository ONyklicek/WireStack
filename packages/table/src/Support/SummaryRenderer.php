<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * The table's totals, for the two places they appear.
 *
 * One owner, because they are rendered twice into the same document — the
 * desktop `<tfoot>` inside the `<table>`, and a card footer for the width where
 * that table is hidden — and because a write now has to be able to re-render
 * them **on their own**.
 *
 * That is what this exists for. A total is computed over the whole filtered set,
 * so an edit to one row moves it, and it lives outside every row: a table with a
 * summary refused row partials outright until the footers had anchors of their
 * own. Now a write queues the row *and* the totals, and the totals are still
 * computed once for both footers ({@see WithTable::computeTableSummaries()}
 * memoises per render).
 *
 * Unlike the row and the card this renders a view per call rather than filling a
 * compiled shape — and that is fine here where it would not be there: the footer
 * is rendered once per table, not once per record, so it is one view render
 * either way.
 */
final class SummaryRenderer
{
    private function __construct(
        private readonly Table $table,
        private readonly mixed $component,
        private readonly TableRenderPlan $plan,
    ) {}

    public static function for(Table $table, mixed $component, TableRenderPlan $plan): self
    {
        return new self($table, $component, $plan);
    }

    /** The `<tfoot>` under the desktop table. */
    public function desktop(): string
    {
        $columns = $this->plan->columns();
        $actions = $this->plan->actions();
        $layout = $this->plan->layout();

        return view('wire-table::tables.partials.summary-footer', [
            ...$this->shared(),
            'partialAnchor' => $this->anchor('summary'),
            'isSelectable' => $this->plan->row()->isSelectable,
            'hasActions' => $actions->hasAny,
            'actionsPosition' => $actions->position,
            'cellPadding' => $layout->cellPadding,
            'isBordered' => $layout->isBordered,
            'visibleColumns' => $columns->visible,
            'colSpan' => $columns->colSpan,
        ])->render();
    }

    /** The same totals under the stacked card list. */
    public function mobile(): string
    {
        return view('wire-table::tables.partials.summary-footer-mobile', [
            ...$this->shared(),
            'partialAnchor' => $this->anchor('summary-mobile'),
            'visibleColumns' => $this->plan->columns()->visible,
        ])->render();
    }

    /**
     * The anchors for one group's subtotal rows, in order.
     *
     * One per row rather than one for the group: a subtotal is as many `<tr>`s as
     * the widest column has summaries, and `wire:partial` addresses a single
     * element. Wrapping them would mean a `<tbody>` per group — valid HTML, but a
     * change to the table's structure, and the delegated `wireRecordActions`
     * controller is mounted on the one `<tbody>` precisely so a nested table
     * cannot be mistaken for it.
     *
     * @return array<int, string>
     */
    public function groupAnchors(mixed $groupValue): array
    {
        if (! $this->table->usesRowPartials()) {
            return [];
        }

        $name = self::groupPartialName($groupValue);

        return array_map(
            fn (int $i): string => ' wire:partial="'.$name.'-'.$i.'"',
            range(0, max(0, $this->groupRowCount($groupValue) - 1)),
        );
    }

    /**
     * One group's subtotal rows, re-rendered on their own.
     *
     * @return array<string, string> anchor name => that row's markup
     */
    public function group(mixed $groupValue): array
    {
        $summaries = $this->component->computeGroupSummaries($groupValue);
        $name = self::groupPartialName($groupValue);
        $columns = $this->plan->columns();
        $actions = $this->plan->actions();
        $layout = $this->plan->layout();

        $html = view('wire-table::tables.partials.group-subtotal', [
            'table' => $this->table,
            'component' => $this->component,
            'groupSummaries' => $summaries,
            'visibleColumns' => $columns->visible,
            'colSpan' => $columns->colSpan,
            'cellPadding' => $layout->cellPadding,
            'isBordered' => $layout->isBordered,
            'isSelectable' => $this->plan->row()->isSelectable,
            'hasActions' => $actions->hasAny,
            'actionsPosition' => $actions->position,
            'partialAnchors' => $this->groupAnchors($groupValue),
        ])->render();

        // The partial emits every subtotal row in one string; each is its own
        // region, so they are split back apart on the anchor that opens them.
        $rows = [];

        foreach (explode('<tr wire:partial="', $html) as $index => $chunk) {
            if ($index === 0) {
                continue;
            }

            [$anchor] = explode('"', $chunk, 2);
            $rows[$anchor] = '<tr wire:partial="'.$chunk;
        }

        return $rows;
    }

    /**
     * A group's value can be any scalar a column holds — a date, a name with a
     * quote in it — so the name it is addressed by is a hash rather than the
     * value.
     */
    public static function groupPartialName(mixed $groupValue): string
    {
        return 'group-'.crc32((string) $groupValue);
    }

    private function groupRowCount(mixed $groupValue): int
    {
        $rows = 0;

        foreach ($this->component->computeGroupSummaries($groupValue) as $summaryList) {
            $rows = max($rows, count($summaryList));
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function shared(): array
    {
        $scope = $this->component->getSummaryScope();

        return [
            'table' => $this->table,
            'component' => $this->component,
            'summaries' => $this->component->computeTableSummaries($scope),
            'subRowGrandTotals' => $this->component->computeSubRowGrandTotals($scope),
            'summaryScope' => $scope,
            'summaryScopeOptions' => $this->component->getSummaryScopeOptions(),
        ];
    }

    /**
     * A whole attribute or nothing, so a table that does not send rows pays no
     * byte for the anchor — the same shape the row and the card use.
     */
    private function anchor(string $name): string
    {
        return $this->table->usesRowPartials() ? ' wire:partial="'.$name.'"' : '';
    }
}
