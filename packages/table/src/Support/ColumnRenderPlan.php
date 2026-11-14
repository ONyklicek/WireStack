<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Contracts\RendersCellPanel;
use NyonCode\WireTable\Contracts\ShowsTableColumns;
use NyonCode\WireTable\Enums\ColumnFilterLayout;
use NyonCode\WireTable\Table;

/**
 * Which columns one render shows, and everything read off them.
 *
 * The other half of {@see TableRenderPlan}. Distinct from {@see ColumnSet},
 * which is the table's CONFIGURED columns and answers config-level questions:
 * this is what a particular render resolved for a particular user, so it needs
 * the host's per-user visibility, and it carries the compiled markup that render
 * will splice per row.
 *
 * Three different questions get asked of the same column list, and they are
 * deliberately not the same filter — getting them confused is the easy mistake:
 *
 *  - **visible** — `canView()` AND not toggled off: what the body renders;
 *  - **toggleable** — `canView()` alone, so a column the user hid stays in the
 *    menu that would switch it back on;
 *  - **filterable** — visible AND filterable: a filter on a column nobody can see
 *    would narrow the set for reasons the user cannot inspect.
 */
final class ColumnRenderPlan
{
    /**
     * @param  array<int, Column>  $visible
     * @param  array<string, array<string, mixed>>  $meta  Keyed by column name.
     * @param  list<string>  $fillable  Column names a fill drag may write.
     * @param  array<int, Column>  $filterable
     * @param  array<int, Column>  $subRow
     * @param  array<int, Column>  $visibleSubRow
     * @param  int  $colSpan  Width of a full-width row: the empty state, a group
     *                        subtotal, the summary footer.
     * @param  array<int, Column>  $toggleable
     * @param  int  $visibleToggleableCount  How many toggleables are shown now.
     * @param  list<Column>  $mobileSortable
     */
    private function __construct(
        public readonly array $visible,
        public readonly bool $hasVisible,
        public readonly array $meta,
        public readonly array $fillable,
        public readonly bool $isFillEnabled,
        public readonly array $filterable,
        public readonly bool $hasFilters,
        public readonly array $subRow,
        public readonly array $visibleSubRow,
        public readonly bool $hasCopyable,
        public readonly int $colSpan,
        public readonly array $toggleable,
        public readonly int $visibleToggleableCount,
        // Whether the filterable columns carry a funnel that opens their filter
        // in a popover (ColumnFilterLayout::Popover) — in which case there is no
        // filter row, and $hasFilters is false.
        public readonly bool $hasFilterPopovers,
        public readonly bool $hasToggles,
        public readonly array $mobileSortable,
        public readonly bool $hasMobileSort,
        // Whether any column is pinned to the start (Column::pinned(), or the
        // user's own through Table::pinnableColumns()) — the selection column
        // then sticks with them, and wire-table-columns.js measures the offsets.
        public readonly bool $hasPinned = false,
        public readonly bool $isResizable = false,
        /** @var list<string> Names of the pinned columns, in the order they are drawn. */
        public readonly array $pinnedNames = [],
        /** @var list<RendersCellPanel> Visible columns whose cells share markup drawn once. */
        public readonly array $cellPanels = [],
    ) {}

    /**
     * @param  ShowsTableColumns|mixed  $component  The host. Typed `mixed` so a
     *                                              consumer's component needs no `implements` to keep working; the contract
     *                                              is what it must be able to answer, and what a test double implements.
     */
    public static function resolve(Table $table, mixed $component): self
    {
        $visible = array_filter(
            $table->getColumns(),
            fn ($c) => $c->canView() && $component->isColumnVisible($c->getName()),
        );

        // Pinned columns are drawn first, in their declared order: a column
        // stuck to the start of the table has to BE at the start, or the ones
        // before it would scroll underneath it. Keys are kept.
        // A host that predates the user's own pins and widths (a component of an
        // application's own over ShowsTableColumns) still gets the declared pins.
        $pinned = is_callable([$component, 'isColumnPinned'])
            ? array_filter($visible, fn ($c) => (bool) call_user_func([$component, 'isColumnPinned'], $c))
            : array_filter($visible, fn ($c) => $c->isPinned());
        $visible = $pinned + $visible;
        $widths = is_callable([$component, 'tableColumnWidths']) ? (array) call_user_func([$component, 'tableColumnWidths']) : [];

        // Sub-rows render through the same column partials, so their columns are
        // resolved here too — but only when the table has any.
        $hasSubRows = $table->hasSubRows();
        $subRow = $hasSubRows ? $table->getSubRowColumns() : [];
        $visibleSubRow = $hasSubRows ? $table->getViewableSubRowColumns() : [];

        $toggleable = array_filter(
            $table->getColumns(),
            fn ($c) => $c->isToggleable() && $c->canView(),
        );

        $filterable = array_filter(
            $table->getColumns(),
            fn ($c) => $c->canView() && $c->isFilterable() && $component->isColumnVisible($c->getName()),
        );

        // Fillable means WRITABLE, so a table can have the handle switched on and
        // still have nothing to fill — which is what isFillEnabled catches.
        $fillable = array_values(array_map(
            fn ($c) => $c->getName(),
            array_filter($visible, fn ($c) => $c->isFillable()),
        ));

        // Sorting without a header row. The stacked card view hides the header
        // that holds the sort buttons; the list layout never renders one at all.
        // Either way the control has to exist somewhere else — and under the list
        // layout it is the only sort UI there is, not a phone's consolation.
        $mobileSortable = (($table->isStackedOnMobile() || ! $table->rendersTable()) && $table->isSortable())
            ? array_values(array_filter($visible, fn ($c) => $c->isSortable()))
            : [];

        return new self(
            visible: $visible,
            hasVisible: count($visible) > 0,
            meta: self::meta($table, $visible, array_map(fn ($c) => $c->getName(), $pinned), $widths),
            fillable: $fillable,
            isFillEnabled: $table->isFillHandleEnabled() && $fillable !== [],
            filterable: $filterable,
            // The filter ROW: absent when the filters open from the header instead.
            hasFilters: count($filterable) > 0 && $table->getColumnFilterLayout() === ColumnFilterLayout::Row,
            hasFilterPopovers: count($filterable) > 0 && $table->getColumnFilterLayout() === ColumnFilterLayout::Popover,
            subRow: $subRow,
            visibleSubRow: $visibleSubRow,
            // Whether any cell renders a copy button, and so whether the delegated
            // clipboard controller is worth shipping. Sub-rows count: they render
            // through the same partials, and it is one document listener either way.
            hasCopyable: array_filter($visible, fn ($c) => $c->isCopyable()) !== []
                || array_filter($visibleSubRow, fn ($c) => $c->isCopyable()) !== [],
            colSpan: ($table->isSelectable() ? 1 : 0)
                + count($visible)
                + ($table->hasActions() ? 1 : 0)
                + ($hasSubRows ? 1 : 0),
            toggleable: $toggleable,
            visibleToggleableCount: count(array_filter(
                $toggleable,
                fn ($c) => $component->isColumnVisible($c->getName()),
            )),
            hasToggles: count($toggleable) > 0,
            mobileSortable: $mobileSortable,
            hasMobileSort: count($mobileSortable) > 0,
            hasPinned: $pinned !== [],
            isResizable: $table->hasResizableColumns(),
            pinnedNames: array_values(array_map(fn ($c) => $c->getName(), $pinned)),
            cellPanels: array_values(array_filter($visible, fn ($c) => $c instanceof RendersCellPanel)),
        );
    }

    /**
     * Column-static render metadata, resolved once per column.
     *
     * The point is the arithmetic: these are per-COLUMN answers that a naive view
     * asks per CELL, so a 20-row × 25-column page called each getter 500 times
     * for 25 distinct results. Keyed by column name, read by header and body.
     *
     * `cell` is the column's whole `<td>`, compiled to a {@see Skeleton} with a
     * slot where the record's content goes. Every attribute on that tag is
     * column-static — only what sits BETWEEN the tags varies by record — so the
     * row loop splices content into a prepared string instead of re-rendering the
     * same opening tag once per cell. It is also how a cell is emitted with no
     * whitespace between its tags: each run of whitespace is one DOM text node
     * and the morph walks every one of them on every commit (see
     * `TablePayloadFuseTest`).
     *
     * @param  array<int, Column>  $visible
     * @return array<string, array<string, mixed>>
     */
    /**
     * @param  array<int, Column>  $visible
     * @param  array<int, string>  $pinned  names of the pinned columns
     * @param  array<string, int>  $widths  user widths in px, by column name
     * @return array<string, array<string, mixed>>
     */
    private static function meta(Table $table, array $visible, array $pinned = [], array $widths = []): array
    {
        $sticky = StickyColumn::on('start');

        $cellPadding = $table->getCellPadding();
        $borderClass = $table->isBordered()
            ? 'border border-gray-200 dark:border-gray-700'
            : '';

        $meta = [];

        foreach ($visible as $column) {
            $name = $column->getName();

            $entry = [
                'wrapClass' => $column->shouldWrap() ? '' : 'whitespace-nowrap',
                'alignment' => $column->getAlignmentClass(),
                'responsive' => $column->getResponsiveClasses(),
                'editable' => $column->isEditable(),
                'responsiveDisplay' => $column->hasResponsiveDisplay(),
                // A width the user dragged wins over the declared one.
                'widthStyles' => isset($widths[$name])
                    ? 'width: '.$widths[$name].'px; min-width: '.$widths[$name].'px'
                    : $column->getWidthStyles(),
                'pinned' => in_array($name, $pinned, true),
                // Author-supplied cell/header attributes, resolved here with the
                // rest of the column-static metadata rather than per cell.
                'extraCell' => $column->getExtraAttributes(),
                'extraHeader' => collect($column->getExtraHeaderAttributes())
                    ->map(fn ($v, $k) => e($k).'="'.e($v).'"')
                    ->implode(' '),
            ];

            $entry['cell'] = Skeleton::compile(
                view('wire-table::tables.partials.body-cell', [
                    'cellPadding' => $cellPadding,
                    'wrapClass' => $entry['wrapClass'],
                    'borderClass' => $borderClass,
                    'alignment' => $entry['alignment'],
                    'responsive' => $entry['responsive'],
                    'name' => $name,
                    'extraAttributes' => $entry['extraCell'],
                    'pinned' => $entry['pinned'] ? $sticky : null,
                    'content' => Skeleton::slot('content'),
                ])->render(),
                'content',
            );

            $meta[$name] = $entry;
        }

        return $meta;
    }
}
