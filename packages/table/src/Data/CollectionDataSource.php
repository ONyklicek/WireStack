<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Data\ArrayRecord;
use NyonCode\WireCore\Core\Data\DataSource;
use NyonCode\WireCore\Core\Data\PagingMode;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Exceptions\UnsupportedQueryAspectException;

/**
 * A table over rows already in memory — arrays, DTOs, an API response.
 *
 * This is the proof the abstraction is one. An interface with a single
 * implementation is indirection; the capability policy in particular cannot be
 * exercised without a source that genuinely cannot do something, and this one
 * cannot do four things.
 *
 * **What it honours.** `QueryPlan`'s clauses are declarative — a `FilterClause`
 * is a column, an operator and a value, not a closure — so filtering, sorting
 * and searching are a `Collection` away. **What it refuses**, loudly, is
 * anything that is really SQL: a raw `sqlExpression`, a clause that reaches
 * through a relation, and subquery aggregates. It also has no cheap change
 * token, and says so by returning null rather than inventing one.
 *
 * A table over this is therefore a *restricted* table, and that is a documented
 * property rather than a surprise: a column with `->sortUsing(fn (Builder $q))`
 * or a relation path throws here instead of quietly sorting by nothing.
 */
final class CollectionDataSource implements DataSource
{
    /** @var Collection<int, array<string, mixed>> */
    private readonly Collection $rows;

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  string  $keyName  Which key identifies a row.
     */
    public function __construct(iterable $rows, private readonly string $keyName = 'id')
    {
        $this->rows = new Collection($rows);
    }

    public function paginate(QueryPlan $plan, PagingRequest $paging): LengthAwarePaginatorContract|PaginatorContract|CursorPaginator
    {
        if ($paging->mode === PagingMode::Cursor) {
            // Keyset paging over an in-memory list would be offset paging
            // wearing a cursor. Refusing beats pretending.
            throw UnsupportedQueryAspectException::notDeclared('cursor paging', self::class);
        }

        $matched = $this->apply($plan);
        $total = $matched->count();
        $perPage = $paging->perPage > 0 ? $paging->perPage : max(1, $total);
        $page = $paging->page ?? Paginator::resolveCurrentPage($paging->pageName);

        $slice = $matched->forPage($page, $perPage)->values();

        $options = [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $paging->pageName,
        ];

        if ($paging->mode === PagingMode::Simple) {
            // Simple paging promises no total, so it must not report one: the
            // extra row is how "has more pages" is answered without a count.
            return new Paginator(
                $matched->forPage($page, $perPage + 1)->values()->take($perPage + 1),
                $perPage,
                $page,
                $options,
            );
        }

        return new LengthAwarePaginator($slice, $total, $perPage, $page, $options);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function get(QueryPlan $plan): Collection
    {
        return $this->apply($plan)->values();
    }

    public function count(QueryPlan $plan): int
    {
        return $this->apply($plan)->count();
    }

    public function resolveRecord(int|string $key): ?RecordContract
    {
        $row = $this->rows->first(fn (array $row): bool => ($row[$this->keyName] ?? null) == $key);

        return $row === null ? null : new ArrayRecord($row, $this->keyName);
    }

    /**
     * @param  array<int, int|string>  $keys
     * @return Collection<int, RecordContract>
     */
    public function resolveRecords(array $keys): Collection
    {
        return $this->rows
            ->filter(fn (array $row): bool => in_array($row[$this->keyName] ?? null, $keys, false))
            ->map(fn (array $row): RecordContract => new ArrayRecord($row, $this->keyName))
            ->values();
    }

    /**
     * What an in-memory list can answer.
     *
     * Note what is absent as much as what is present: no `SqlExpression`, no
     * `Joinable`, no `Aggregateable`, no `ChangeToken`.
     */
    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet(
            Capability::Searchable,
            Capability::Sortable,
            Capability::Filterable,
            Capability::Paginable,
        );
    }

    /**
     * No cheap way to know whether the rows changed — and null is the contract's
     * word for exactly that, so the caller compares rows itself.
     */
    public function changeToken(QueryPlan $plan): ?string
    {
        return null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function apply(QueryPlan $plan): Collection
    {
        $this->guard($plan);

        $rows = $this->rows;

        foreach ($plan->filters as $filter) {
            $rows = $rows->filter(fn (array $row): bool => $this->matches($row, $filter));
        }

        foreach (array_reverse($plan->sortClauses) as $sort) {
            // Reversed, because a stable sort applied last wins: sorting by the
            // least significant clause first leaves the most significant one on
            // top, which is what a multi-column ORDER BY means.
            $rows = $sort->direction === 'desc'
                ? $rows->sortByDesc(fn (array $row): mixed => $row[$sort->column] ?? null)
                : $rows->sortBy(fn (array $row): mixed => $row[$sort->column] ?? null);
        }

        return $rows;
    }

    /**
     * Refuse every aspect of the plan this source cannot honour, before it
     * returns rows that would silently be wrong.
     */
    private function guard(QueryPlan $plan): void
    {
        foreach ([...$plan->filters, ...$plan->sortClauses, ...$plan->searchClauses] as $clause) {
            if (($clause->sqlExpression ?? null) !== null) {
                throw UnsupportedQueryAspectException::notDeclared('sql_expression', self::class);
            }

            if (($clause->isRelation ?? false) === true) {
                throw UnsupportedQueryAspectException::notDeclared('joinable', self::class);
            }
        }

        if ($plan->hasAggregates()) {
            throw UnsupportedQueryAspectException::notDeclared('aggregateable', self::class);
        }

        if ($plan->hasJoins()) {
            throw UnsupportedQueryAspectException::notDeclared('joinable', self::class);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matches(array $row, FilterClause $filter): bool
    {
        $value = $row[$filter->column] ?? null;

        return match ($filter->operator) {
            '=' => $value == $filter->value,
            '!=', '<>' => $value != $filter->value,
            '>' => $value > $filter->value,
            '>=' => $value >= $filter->value,
            '<' => $value < $filter->value,
            '<=' => $value <= $filter->value,
            'like' => is_string($value) && str_contains(
                mb_strtolower($value),
                mb_strtolower(trim((string) $filter->value, '%')),
            ),
            'in' => is_array($filter->value) && in_array($value, $filter->value, false),
            default => throw UnsupportedQueryAspectException::notDeclared(
                "filter operator [{$filter->operator}]",
                self::class,
            ),
        };
    }
}
