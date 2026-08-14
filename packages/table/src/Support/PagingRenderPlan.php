<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Table;

/**
 * Where this page sits in the whole result set.
 *
 * Part of {@see TableRenderPlan}, and the first slice that needs the records
 * themselves rather than the table's configuration.
 *
 * ### Why the counts are not simply `count()`
 *
 * The four things `WithTable::getTableRecords()` can return do not answer the
 * same questions, and none of them fails loudly when asked the wrong one — both
 * abstract paginators `__call`-forward to the underlying collection, so a wrong
 * call surfaces as a `BadMethodCallException` from `Collection` rather than a
 * type error:
 *
 * | | `total()` | `firstItem()` / `lastItem()` |
 * |---|---|---|
 * | `LengthAwarePaginator` (default) | yes | yes |
 * | `Paginator` — `Table::simplePagination()` | **no** | yes |
 * | `CursorPaginator` — `Table::cursorPagination()` | **no** | **no** |
 * | `Collection` — unpaginated, reorder mode, lazy-not-ready | **no** | **no** |
 *
 * So every read here is gated on {@see $hasPaginator}, which is true only for
 * the length-aware case. `total()` is never reached for the other three, and
 * `firstItem()`/`lastItem()` never for the last two.
 *
 * ### Three modes, not a boolean
 *
 * The view used to branch on `instanceof LengthAwarePaginator` alone, so both
 * other modes fell down the "not paginated" path: no links at all, and a
 * "showing 1 – N of N" counting only the current page. Simple pagination was the
 * sharper loss, since its `firstItem()`/`lastItem()` do work.
 *
 * So the three questions are asked separately:
 *
 *  - {@see $knowsTotal} — may `total()` be called (length-aware only);
 *  - {@see $knowsRange} — may `firstItem()`/`lastItem()` be called (not cursor);
 *  - {@see $hasLinks} — is there more than one page to offer at all.
 *
 * Each mode gets the controls it can actually drive, which is why
 * {@see $linksView} is a decision rather than a constant. Cursor is the one that
 * needed building: Livewire's pagination trait is page-based
 * (`previousPage`/`nextPage`/`gotoPage`) with no cursor equivalent, so its
 * buttons hand the paginator's own encoded cursor back through
 * `WithTable::setTableCursor()`, and the cursor lives in table state where the
 * rest of this table's paging already lives.
 */
final class PagingRenderPlan
{
    /**
     * @param  bool  $isPaginated  The table's configuration, independent of what
     *                             this page's records turned out to be.
     * @param  bool  $hasPaginator  Whether the records can answer for the whole
     *                              set — length-aware only. Kept as the name the
     *                              view has always used for that question.
     * @param  bool  $knowsRange  Whether an item offset may be asked for.
     * @param  bool  $hasLinks  Whether there is a second page to offer.
     * @param  string  $linksView  The partial `links()` should render — the full
     *                             numbered one, or prev/next for simple.
     * @param  int  $recordCount  The whole set when known, otherwise this page.
     * @param  int  $rangeFrom  1-based offset of this page's first row.
     * @param  int  $rangeTo  …and its last.
     * @param  int  $headerRowCount  Header rows preceding the body in the ARIA
     *                               row numbering — miss it and every body index
     *                               is off by one.
     */
    private function __construct(
        public readonly bool $isPaginated,
        public readonly bool $hasPaginator,
        public readonly bool $knowsRange,
        public readonly bool $hasLinks,
        public readonly string $linksView,
        public readonly int $recordCount,
        public readonly bool $isEmptyDueToFilter,
        public readonly int $rangeFrom,
        public readonly int $rangeTo,
        public readonly int $headerRowCount,
    ) {}

    /**
     * `$hasActiveFilters` and `$hasColumnFilters` are passed rather than read off
     * the sibling plans, so this stays testable on its own and the dependency is
     * visible in the signature.
     *
     * @param  LengthAwarePaginator<int, Model>|Paginator<int, Model>|CursorPaginator<int, Model>|Collection<int, Model>  $records
     */
    public static function resolve(
        Table $table,
        LengthAwarePaginator|Paginator|CursorPaginator|Collection $records,
        bool $hasActiveFilters,
        bool $hasColumnFilters,
    ): self {
        $hasPaginator = $records instanceof LengthAwarePaginator;

        // A simple paginator has no total() but does have working item offsets;
        // a cursor paginator has neither. Both can still say whether they have
        // more pages.
        $isSimple = ! $hasPaginator && $records instanceof Paginator;
        $knowsRange = $hasPaginator || $isSimple;

        $recordCount = $hasPaginator ? $records->total() : $records->count();

        return new self(
            isPaginated: $table->isPaginated(),
            hasPaginator: $hasPaginator,
            knowsRange: $knowsRange,
            hasLinks: $records instanceof Collection ? false : $records->hasPages(),
            linksView: match (true) {
                $hasPaginator => 'wire-table::tables.partials.pagination',
                $isSimple => 'wire-table::tables.partials.pagination-simple',
                // A cursor cannot call previousPage()/nextPage(); its controls
                // hand the encoded cursor back through setTableCursor().
                default => 'wire-table::tables.partials.pagination-cursor',
            },
            recordCount: $recordCount,
            // "No results match your filters" rather than "nothing here yet" —
            // the empty state that offers a way back.
            isEmptyDueToFilter: $hasActiveFilters && $recordCount === 0,
            rangeFrom: $knowsRange
                ? ($records->firstItem() ?? 0)
                : ($records->count() > 0 ? 1 : 0),
            rangeTo: $knowsRange
                ? ($records->lastItem() ?? 0)
                : $records->count(),
            headerRowCount: 1 + ($hasColumnFilters ? 1 : 0),
        );
    }
}
