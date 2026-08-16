<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use NyonCode\WireCore\Foundation\Concerns\InteractsWithPartials;
use NyonCode\WireTable\Table;

/**
 * One table row, assembled from pieces Blade compiled once.
 *
 * Every piece here is the output of a compiled partial — the `<tr>` tag
 * ({@see RowRenderPlan::$rowSkeleton}), the selection cell, the three expander
 * shapes, the action cell, the per-column cells. Nothing below writes markup;
 * it decides which pieces a record gets and in what order, which is what the row
 * loop's `@if`s used to do in Blade.
 *
 * **Why it moved out of Blade.** Two reasons, and the second is the one that
 * pays:
 *
 *  - a row has to be renderable for ONE record, so a write can send back the row
 *    it changed instead of the page —
 *    {@see InteractsWithPartials}. In the
 *    loop the row body could only be reached by rendering the whole table;
 *  - the loop's own conditionals emitted Livewire morph markers on every row:
 *    459–999 B of them per row depending on the table, measured across three
 *    previews. PHP emits none.
 *
 * **What replaced them.** Those markers were doing real work — they bracketed
 * the children whose *presence* varies, so a morph could pair them by position.
 * Removing them was only safe once the variable children carried keys instead:
 * `ctx-`, `sel-`, `exp-` on the row's leading children, and `act-{key}-{name}`
 * on every record-scoped action button. Both landed and were driver-gated before
 * this class existed, which is the whole reason it can exist —
 * `RowMorphKeysTest` is what keeps them there.
 *
 * The cells were already spliced in PHP for the same reason, so this finishes a
 * job rather than starting one.
 */
final class RowRenderer
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

    /**
     * @param  int  $rowIndex  the record's position on the page, which the row's
     *                         roving tabindex and ARIA row index are counted from
     */
    public function render(Model $record, int $rowIndex): string
    {
        $row = $this->plan->row();
        $columns = $this->plan->columns();
        $actions = $this->plan->actions();

        $recordKey = (string) $record->{$this->table->getPrimaryKey()};
        $key = e($recordKey);
        $keyJs = Js::from($recordKey)->toHtml();

        $html = $row->rowSkeleton->fill([
            'rowClass' => e($this->table->getRowClasses($record, $rowIndex)),
            'keyJs' => $keyJs,
            'tabindex' => $rowIndex === 0 ? '0' : '-1',
            'rowIndex' => (string) $rowIndex,
            'ariaRowIndex' => (string) ($this->plan->paging()->headerRowCount + $this->plan->paging()->rangeFrom + $rowIndex),
            'key' => $key,
        ]);

        // The right-click menu. Empty for a record with no visible action, which
        // is the one child of a row whose presence genuinely varies per record.
        $html .= $this->table->getRowContextMenuPanel($record);

        if ($row->isSelectable) {
            $html .= $row->selectionCellSkeleton->fill(['keyJs' => $keyJs, 'key' => $key]);
        }

        if ($this->plan->shell()->hasSubRows) {
            $html .= $this->table->getSubRowCell(
                $keyJs,
                $key,
                $this->plan->shell()->isSubRowsExpandable && $this->table->hasSubRowsFor($record),
                $this->component->isRowExpanded($recordKey),
            );
        }

        $actionCell = $actions->hasAny
            ? $this->table->getActionCellSkeleton()->fill(['actions' => $this->renderActions($record)])
            : '';

        if ($actions->position === 'start') {
            $html .= $actionCell;
        }

        $html .= $this->renderCells($record, $columns);

        if ($actions->position !== 'start') {
            $html .= $actionCell;
        }

        return $html.'</tr>';
    }

    private function renderActions(Model $record): string
    {
        $html = '';

        foreach ($this->plan->actions()->row as $action) {
            $html .= $action->render($record, $this->plan->actions()->click);
        }

        return $html;
    }

    /**
     * The record's cells, one per visible column.
     *
     * A record url turns every non-editable cell into a link to the record; an
     * editable one keeps its own interaction, or the link would swallow the click
     * that starts the edit.
     */
    private function renderCells(Model $record, ColumnRenderPlan $columns): string
    {
        $recordUrl = $this->table->getRecordUrl($record);
        $linkOpen = $recordUrl
            ? '<a href="'.e($recordUrl).'" class="hover:text-primary-600 dark:hover:text-primary-400">'
            : '';

        $html = '';

        foreach ($columns->visible as $column) {
            $meta = $columns->meta[$column->getName()];

            $cell = $meta['responsiveDisplay']
                ? $column->renderResponsiveCell($record)
                : $column->renderCellFast($record);

            if ($linkOpen !== '' && ! $meta['editable']) {
                $cell = $linkOpen.$cell.'</a>';
            }

            $html .= $meta['cell']->fill(['content' => $cell]);
        }

        return $html;
    }
}
