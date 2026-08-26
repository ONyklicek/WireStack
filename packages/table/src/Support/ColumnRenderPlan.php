<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Contracts\ShowsTableColumns;
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
        public readonly bool $hasToggles,
        public readonly array $mobileSortable,
        public readonly bool $hasMobileSort,
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

        // Sub-rows render through the same column partials, so their columns are
        // resolved here too — but only when the table has any.
        $hasSubRows = $table->hasSubRows();
        $subRow = $hasSubRows ? $table->getSubRowColumns() : [];
        $visibleSubRow = $hasSubRows
            ? array_filter($subRow, fn ($c) => $c->canView())
            : [];

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

        // Sorting on a phone: the stacked card view hides the header row that
        // holds the sort buttons, so the control has to exist somewhere else.
        $mobileSortable = ($table->isStackedOnMobile() && $table->isSortable())
            ? array_values(array_filter($visible, fn ($c) => $c->isSortable()))
            : [];

        return new self(
            visible: $visible,
            hasVisible: count($visible) > 0,
            meta: self::meta($table, $visible),
            fillable: $fillable,
            isFillEnabled: $table->isFillHandleEnabled() && $fillable !== [],
            filterable: $filterable,
            hasFilters: count($filterable) > 0,
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
    private static function meta(Table $table, array $visible): array
    {
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
                    'content' => Skeleton::slot('content'),
                ])->render(),
                'content',
            );

            $meta[$name] = $entry;
        }

        return $meta;
    }
}
