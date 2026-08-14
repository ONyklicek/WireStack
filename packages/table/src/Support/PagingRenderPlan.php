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
 * ### A known gap, deliberately preserved
 *
 * That gate is a boolean over what is really three cases, and the consequence is
 * visible: a simple- or cursor-paginated table takes the fallback branch
 * everywhere, so it renders no pagination links and a "showing 1 – N of N" line
 * counting only its own page. Simple pagination is the sharper loss, because its
 * `firstItem()`/`lastItem()` do work.
 *
 * This class reproduces that exactly rather than fixing it. The extraction it
 * belongs to is warranted by producing byte-identical output; changing what a
 * simple-paginated table renders is a separate change with its own gate. See
 * `architecture/plans/livewire-4-migration-and-performance.md` §5.2.
 */
final class PagingRenderPlan
{
    /**
     * @param  bool  $isPaginated  The table's configuration, independent of what
     *                             this page's records turned out to be.
     * @param  bool  $hasPaginator  Whether the records can answer for the whole
     *                              set — length-aware only.
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

        $recordCount = $hasPaginator ? $records->total() : $records->count();

        return new self(
            isPaginated: $table->isPaginated(),
            hasPaginator: $hasPaginator,
            recordCount: $recordCount,
            // "No results match your filters" rather than "nothing here yet" —
            // the empty state that offers a way back.
            isEmptyDueToFilter: $hasActiveFilters && $recordCount === 0,
            rangeFrom: $hasPaginator
                ? ($records->firstItem() ?? 0)
                : ($records->count() > 0 ? 1 : 0),
            rangeTo: $hasPaginator
                ? ($records->lastItem() ?? 0)
                : $records->count(),
            headerRowCount: 1 + ($hasColumnFilters ? 1 : 0),
        );
    }
}
