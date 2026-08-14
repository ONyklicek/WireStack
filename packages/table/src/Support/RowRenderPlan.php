<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Table;

/**
 * The row itself: its compiled markup, and everything selection needs.
 *
 * The last group of {@see TableRenderPlan}, and the only one that reads a
 * sibling — {@see InteractionRenderPlan}, for the three things the row's opening
 * tag is shaped by. That edge is real rather than incidental: whether the row is
 * keyboard-navigable decides its `tabindex` and its role, and whether an
 * active-row marker exists decides half of its class binding.
 *
 * ### Static once, dynamic per row
 *
 * Two skeletons here are the engine's central principle in practice — build the
 * markup once per TABLE and splice per record, rather than rendering a view per
 * row:
 *
 *  - **`rowSkeleton`** is the `<tr>` opening tag. Every condition on it is a
 *    property of the table, so the row has exactly one shape; what varies per
 *    record arrives through six slots. It is an opening tag rather than a whole
 *    row because the cells between it and `</tr>` are spliced from their own
 *    per-column skeletons (see {@see ColumnRenderPlan::meta()}).
 *  - **`selectionCellSkeleton`** is the same move for the checkbox cell, except
 *    the markup stays in its partial and the {@see Table} renders it once.
 *
 * ### The row class binding
 *
 * One `:class` expression per row, merging every dynamic row state that has to
 * survive a Livewire morph — the selection tint and the active-row marker. Both
 * are Alpine bindings rather than classes toggled from JS, precisely so the
 * roundtrip a click triggers cannot wash them off. `%key%` is substituted with
 * the record key per row.
 *
 * ### Selection
 *
 * Selection lives client-side and is entangled deferred, so a checkbox click
 * costs no roundtrip. {@see $selectionSyncLive} is the exception: when the footer
 * renders summaries, changes have to be committed (debounced) or the
 * selection-scope totals and the scope toggle go stale.
 *
 * The announcements are whole sentences because only the server can translate
 * them; the counts are substituted client-side because the selection is Alpine's.
 */
final class RowRenderPlan
{
    /**
     * @param  string  $selectCheckIcon  Record-invariant chrome, resolved once —
     *                                   the card view echoes the string rather
     *                                   than re-entering the icon directive.
     * @param  string|null  $rowClassBinding  Null when no row state is dynamic.
     * @param  list<string>  $pageRecordKeys
     * @param  array<string, string>  $selectionAnnouncements
     */
    private function __construct(
        public readonly bool $isSelectable,
        public readonly string $selectCheckIcon,
        public readonly bool $hasSummaries,
        public readonly ?string $rowClassBinding,
        public readonly array $pageRecordKeys,
        public readonly bool $selectionSyncLive,
        public readonly Skeleton $rowSkeleton,
        public readonly ?Skeleton $selectionCellSkeleton,
        public readonly array $selectionAnnouncements,
    ) {}

    /**
     * @param  LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>|Collection<int, Model>  $records
     */
    public static function resolve(
        Table $table,
        mixed $component,
        LengthAwarePaginator|Paginator|CursorPaginator|Collection $records,
        InteractionRenderPlan $interaction,
    ): self {
        $isSelectable = $table->isSelectable();

        $bindingParts = [];

        if ($isSelectable) {
            $bindingParts[] = "'bg-primary-50 dark:bg-primary-900/20': isSelected(%key%)";
        }

        if ($interaction->activeRowConfig !== null) {
            $bindingParts[] = '...rowClass(%key%)';
        }

        $rowClassBinding = $bindingParts === []
            ? null
            : '{ '.implode(', ', $bindingParts).' }';

        $pageRecordKeys = [];

        if ($isSelectable) {
            foreach ($records as $pageRecord) {
                $pageRecordKeys[] = (string) $pageRecord->{$table->getPrimaryKey()};
            }
        }

        $hasSummaries = $component->tableHasSummaries();

        return new self(
            isSelectable: $isSelectable,
            selectCheckIcon: $isSelectable ? $table->getSelectionCheckIcon() : '',
            hasSummaries: $hasSummaries,
            rowClassBinding: $rowClassBinding,
            pageRecordKeys: $pageRecordKeys,
            selectionSyncLive: $isSelectable && $hasSummaries,
            rowSkeleton: self::rowSkeleton($rowClassBinding, $interaction, $isSelectable),
            selectionCellSkeleton: $isSelectable ? $table->getSelectionCellSkeleton() : null,
            selectionAnnouncements: [
                'some' => __('wire-table::messages.selection_announce_some', ['count' => ':count', 'total' => ':total']),
                'all' => __('wire-table::messages.selection_announce_all', ['total' => ':total']),
                'none' => __('wire-table::messages.selection_announce_none'),
            ],
        );
    }

    private static function rowSkeleton(
        ?string $rowClassBinding,
        InteractionRenderPlan $interaction,
        bool $isSelectable,
    ): Skeleton {
        return Skeleton::compile(
            view('wire-table::tables.partials.body-row-open', [
                'keyboardNav' => $interaction->keyboardNav,
                'rowClassBinding' => $rowClassBinding,
                'tableRole' => $interaction->tableRole,
                'isSelectable' => $isSelectable,
                'rowClass' => Skeleton::slot('rowClass'),
                'keyJs' => Skeleton::slot('keyJs'),
                'tabindex' => Skeleton::slot('tabindex'),
                'rowIndex' => Skeleton::slot('rowIndex'),
                'ariaRowIndex' => Skeleton::slot('ariaRowIndex'),
                'key' => Skeleton::slot('key'),
            ])->render(),
            'rowClass', 'keyJs', 'tabindex', 'rowIndex', 'ariaRowIndex', 'key',
        );
    }
}
