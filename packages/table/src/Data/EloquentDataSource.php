<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Data\DataSource;
use NyonCode\WireCore\Core\Data\EloquentRecord;
use NyonCode\WireCore\Core\Data\PagingMode;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * The default data source: an Eloquent builder that has already been narrowed.
 *
 * **Why it lives in wire-table and not in core.** The contract is core's; this
 * is not. Everything `TableQueryService::buildQuery()` does after
 * `QueryExecutor::execute()` — the extra eager loads from
 * `Column::getEagerLoadRelations()`, `AggregateSubqueries`, the sub-row presence
 * subquery, `Column::applyFilter()` and the user's own sort/filter closures —
 * needs `Column` objects, and core may not see wire-table (ADR 0025, and the
 * package graph before it). Putting the source here keeps every one of those
 * steps exactly where it is, so the Eloquent path does not change at all.
 *
 * **It takes a built query, not a plan to build one.** That is deliberate for
 * V2.0.a: this owns the *list, count and change-token* half of the read path,
 * and `buildQuery()` stays the plan→builder step. `QueryPlan` still arrives on
 * every call, because that is what {@see capabilities()} is checked against and
 * what a non-Eloquent source will have to honour on its own.
 *
 * @see DataSource
 */
final class EloquentDataSource implements DataSource
{
    /**
     * @param  Builder<Model>  $query  A query already narrowed by TableQueryService.
     */
    public function __construct(private readonly Builder $query) {}

    public function paginate(QueryPlan $plan, PagingRequest $paging): LengthAwarePaginator|PaginatorContract|CursorPaginator
    {
        $perPage = $this->resolvePerPage($paging->perPage);

        return match ($paging->mode) {
            PagingMode::Simple => $this->query->simplePaginate($perPage, ['*'], $paging->pageName, $paging->page),
            // The cursor is handed in rather than read from the request:
            // Livewire's pagination is page-based and has no cursor of its own.
            PagingMode::Cursor => $this->query->cursorPaginate($perPage, ['*'], $paging->pageName, $paging->cursor),
            PagingMode::LengthAware => $this->query->paginate($perPage, ['*'], $paging->pageName, $paging->page),
        };
    }

    /**
     * @return Collection<int, mixed>
     */
    public function get(QueryPlan $plan): Collection
    {
        return $this->query->get();
    }

    /**
     * Delegates to `chunkById()` rather than paging by offset, and passes the
     * qualified key deliberately: an export query carries a LEFT JOIN whenever
     * the table sorts by a relation column, and chunkById's default unqualified
     * `id` cursor is ambiguous against the joined table.
     *
     * @param  callable(Collection<int, mixed>): mixed  $callback
     */
    public function chunk(QueryPlan $plan, int $size, callable $callback): void
    {
        $model = $this->query->getModel();

        $this->query->chunkById(
            $size,
            static fn (Collection $records): mixed => $callback($records),
            $model->getQualifiedKeyName(),
            $model->getKeyName(),
        );
    }

    public function count(QueryPlan $plan): int
    {
        return $this->query->count();
    }

    public function resolveRecord(int|string $key): ?RecordContract
    {
        $model = (clone $this->query)->find($key);

        return $model === null ? null : new EloquentRecord($model);
    }

    /**
     * @param  array<int, int|string>  $keys
     * @return Collection<int, RecordContract>
     */
    public function resolveRecords(array $keys): Collection
    {
        if ($keys === []) {
            // Not merely an optimisation: whereIn() with an empty list is
            // `where 0 = 1` on some grammars and a syntax error on others.
            return new Collection;
        }

        $query = clone $this->query;
        $model = $query->getModel();

        // Qualified, because a selection over a joined query is the ordinary
        // case and both tables commonly have an `id`. Applied as a statement
        // rather than chained, so the builder keeps its Eloquent type.
        $query->whereIn($model->qualifyColumn($model->getKeyName()), $keys);

        return $query->get()
            ->map(fn (Model $record): RecordContract => new EloquentRecord($record))
            ->values();
    }

    /**
     * Everything an Eloquent builder can be asked for.
     *
     * Flat and unconditional on purpose. A source that narrows this — a
     * collection, an API — is the reason the set exists, and the engine refusing
     * an undeclared aspect is only meaningful if the default source declares the
     * full range to refuse against.
     */
    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(
            Capability::Searchable,
            Capability::Sortable,
            Capability::Filterable,
            Capability::Aggregateable,
            Capability::SqlExpression,
            Capability::Joinable,
            Capability::Paginable,
            Capability::SubRows,
            Capability::ChangeToken,
        );
    }

    /**
     * The data half of change detection: how many rows match, and the newest
     * timestamp among them.
     *
     * Extracted from `WithTable::computePollChecksum()` rather than rewritten
     * beside it — every guard below is one that method already had, and a
     * reimplementation lost two of them before this was caught. `null` means
     * the source cannot answer cheaply, which is a real answer: the caller
     * compares rows itself.
     *
     * Deliberately *not* the whole token the table polls on. `WithTable` appends
     * a write generation counter, which is a fact about the application — writes
     * this process made and has not yet seen come back — and no data source can
     * answer for that. `updated_at` is stored to the second, so an edit landing
     * in the same second as the tick that took the last token is otherwise
     * invisible for good, not merely late.
     */
    public function changeToken(QueryPlan $plan): ?string
    {
        $query = clone $this->query;
        // The ordering is irrelevant to an aggregate and only costs a sort.
        // Called as a statement, not chained: reorder() forwards to the base
        // query builder, so chaining loses the Eloquent builder's type.
        $query->reorder();

        $model = $query->getModel();

        if (! $model->usesTimestamps() || $model->getUpdatedAtColumn() === null) {
            return null;
        }

        // Qualified before it is wrapped: once a column sorts or filters through
        // a relation the query carries a join, and two tables both having
        // updated_at is the ordinary case, not the exotic one.
        $updatedAt = $query->getQuery()->getGrammar()->wrap(
            $query->qualifyColumn($model->getUpdatedAtColumn()),
        );

        $base = $query->toBase();
        // Whatever the caller selected is not what an aggregate selects.
        $base->select([]);
        $base->selectRaw("COUNT(*) as wt_count, MAX({$updatedAt}) as wt_max");

        $row = $base->first();

        // Defensive only: an aggregate select with no GROUP BY always comes back
        // with a row — an empty table answers `0|`, not nothing — so this arm is
        // unreachable through Eloquent and exists for a driver that disagrees.
        // @codeCoverageIgnoreStart
        if ($row === null) {
            return null;
        }
        // @codeCoverageIgnoreEnd

        return ($row->wt_count ?? 0).'|'.($row->wt_max ?? '');
    }

    /**
     * Resolve the "all rows" sentinel into a real page size.
     *
     * The sentinel cannot reach the paginator: a negative limit is silently
     * dropped by the query builder, so the rows would be right, while the
     * paginator still divides the total by it, so the page count would be
     * negative. Counting first makes "all" one honest page, and max(1) keeps an
     * empty table from dividing by zero.
     */
    private function resolvePerPage(int $perPage): int
    {
        if ($perPage > 0) {
            return $perPage;
        }

        return max(1, $this->query->toBase()->getCountForPagination());
    }
}
