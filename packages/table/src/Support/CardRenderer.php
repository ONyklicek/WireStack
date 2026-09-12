<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Table;

/**
 * One stacked-layout card, filled from a shell Blade compiled once.
 *
 * The card is a **second full rendering of every record** — the desktop rows are
 * in the same document, hidden by CSS at this width — so it costs what the row
 * costs and it earns the same treatment: the shell's conditions are properties
 * of the table and are decided once, while this fills in what changes per
 * record.
 *
 * It exists for the same reason {@see RowRenderer} does. A write that answers
 * with one row has to answer with that record's card too, or a phone shows the
 * old value while a desktop shows the new one — which is why
 * `Table::usesRowPartials()` refused a stacked table until this existed.
 *
 * Two conditions live here rather than in the shell, because they are per record
 * and would bake wrongly into a compiled shape: whether the record has a url (its
 * title becomes a link) and whether it has sub-rows to expand.
 */
final class CardRenderer
{
    private function __construct(
        private readonly Table $table,
        private readonly mixed $component,
        private readonly TableRenderPlan $plan,
        private readonly MobileCard $card,
    ) {}

    public static function for(Table $table, mixed $component, TableRenderPlan $plan): self
    {
        return new self(
            $table,
            $component,
            $plan,
            $table->getMobileCard(array_values($plan->columns()->visible)),
        );
    }

    public function render(Model $record): string
    {
        $recordKey = (string) $record->{$this->table->getPrimaryKey()};
        $actions = $this->plan->actions();

        // The card's half of the row's inactive state, asked once — the shell's
        // shape follows from it, and the look rides in with $cardClasses.
        $inactive = $this->table->hasInactiveRecords() && $this->table->isRecordInactive($record);

        return $this->table->getMobileCardSkeleton($this->card, $inactive)->fill([
            'cardClasses' => e($this->table->getRowCardClasses($record)),
            'key' => e($recordKey),
            'keyJs' => Js::from($recordKey)->toHtml(),
            'title' => $this->title($record),
            'metric' => $this->card->metric() ? $this->cell($this->card->metric(), $record) : '',
            'subtitle' => $this->card->subtitle() ? $this->cell($this->card->subtitle(), $record) : '',
            'meta' => $this->meta($record),
            'groupActions' => $actions->mobileGroup !== null
                ? $actions->mobileGroup->render($record, $actions->click)
                : '',
            'details' => $this->details($record),
            'actions' => $this->actions($record),
            'subRows' => $this->subRows($record, $recordKey),
        ]);
    }

    /**
     * A record url turns the card's title into a link to the record — the same
     * affordance the desktop cells get, on the one slot a thumb aims at, and out
     * of the same compiled partial ({@see Table::getRecordLinkSkeleton()}).
     */
    private function title(Model $record): string
    {
        $title = $this->card->title();

        if ($title === null) {
            return '';
        }

        $cell = $this->cell($title, $record);
        $url = $this->table->getRecordUrl($record);

        return $url
            ? $this->table->getRecordLinkSkeleton()->fill([
                'url' => e($url),
                'content' => $cell,
            ])
            : $cell;
    }

    private function meta(Model $record): string
    {
        $chip = $this->table->getMobileCardMetaSkeleton();
        $html = '';

        foreach ($this->card->meta() as $column) {
            $html .= $chip->fill(['content' => $this->cell($column, $record)]);
        }

        return $html;
    }

    /**
     * Whatever no slot claimed, as the label/value grid. An odd last item spans
     * both columns rather than leaving a hole beside it.
     */
    private function details(Model $record): string
    {
        $details = $this->card->details();
        $count = count($details);
        $pair = $this->table->getMobileCardDetailSkeleton();
        $html = '';

        foreach (array_values($details) as $index => $column) {
            $isLastOdd = $index === $count - 1 && $count % 2 === 1;

            $html .= $pair->fill([
                'spanClass' => $isLastOdd ? 'col-span-2' : 'col-span-1',
                'label' => e($column->getLabel()),
                'content' => $this->cell($column, $record),
            ]);
        }

        return $html;
    }

    private function actions(Model $record): string
    {
        $actions = $this->plan->actions();
        $html = '';

        foreach ($actions->mobile as $action) {
            $html .= $action->render($record, $actions->click);
        }

        return $html;
    }

    private function subRows(Model $record, string $recordKey): string
    {
        if (! $this->plan->shell()->hasSubRows || ! $this->table->hasSubRowsFor($record)) {
            return '';
        }

        return view('wire-table::tables.partials.sub-rows-mobile', [
            'table' => $this->table,
            'component' => $this->component,
            'record' => $record,
            'recordKey' => $recordKey,
            'visibleSubRowColumns' => $this->plan->columns()->visibleSubRow,
            'isExpanded' => $this->component->isRowExpanded($recordKey),
            'isSubRowsExpandable' => $this->plan->shell()->isSubRowsExpandable,
            'isSelectable' => $this->plan->row()->isSelectable,
        ])->render();
    }

    /**
     * A column that declares a phone-specific rendering gets it; everything else
     * renders the cell it renders on the desktop.
     */
    private function cell(Column $column, Model $record): string
    {
        return $column->hasResponsiveDisplay()
            ? $column->renderMobileCell($record)
            : $column->renderCellFast($record);
    }
}
