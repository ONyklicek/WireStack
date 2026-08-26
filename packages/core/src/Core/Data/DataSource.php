<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Where a dataset comes from, without saying it is a database.
 *
 * Today the read path terminates in `Illuminate\…\Builder` in three places —
 * query building, list/pagination, and count/change-detection — so a bounded
 * context cannot feed a table from a read model, a DTO collection or an API.
 * This contract is that seam. `EloquentDataSource` is the default and keeps the
 * present behaviour byte-for-byte; anything else is opt-in.
 *
 * **The paginator is Laravel's, deliberately.** An earlier sketch returned a
 * `DataResult` of our own, which would have meant touching every view and
 * `paginateQuery()` besides. Returning the framework contract keeps the blast
 * radius to the source itself: a non-Eloquent source assembles a
 * `LengthAwarePaginator` by hand and every consumer downstream is unchanged.
 *
 * **Capabilities are honoured, not assumed.** A source declares what it can
 * answer through {@see capabilities()}, and an aspect the plan asks for that
 * the source has not claimed raises
 * `Exceptions\UnsupportedQueryAspectException`. Never a silently wrong result —
 * a table that cannot really sort must say so rather than return rows in an
 * order the user will trust.
 *
 * @internal Until V2.0.c settles the public surface. `Table::dataSource()`
 *           exists for the framework to route through; the contract may still
 *           grow — record resolution arrives with `RecordContract` in V2.0.b.
 */
interface DataSource
{
    /**
     * The main dataset, as the paginator the views already consume.
     *
     * The union is not laziness: `CursorPaginator` does **not** implement
     * `Paginator` — Laravel models keyset paging as a separate contract — so a
     * single return type would have made cursor mode unreachable. This is the
     * same union `WithTable::paginateQuery()` already declares.
     *
     * @return LengthAwarePaginator<int, mixed>|Paginator<int, mixed>|CursorPaginator<int, mixed>
     */
    public function paginate(QueryPlan $plan, PagingRequest $paging): LengthAwarePaginator|Paginator|CursorPaginator;

    /**
     * The whole dataset, unpaginated — exports, and "select all matching".
     *
     * @return Collection<int, mixed>
     */
    public function get(QueryPlan $plan): Collection;

    /**
     * How many rows the plan matches.
     */
    public function count(QueryPlan $plan): int;

    /**
     * What this source can be asked to do.
     */
    public function capabilities(): CapabilitySet;

    /**
     * An opaque token that changes when the underlying data changes, or null
     * when the source cannot cheaply tell.
     *
     * Null is a real answer and not a failure: it means polling has to compare
     * rows itself rather than short-circuit on a token, which is what a source
     * without a cheap MAX(updated_at) equivalent should say.
     */
    public function changeToken(QueryPlan $plan): ?string;
}
