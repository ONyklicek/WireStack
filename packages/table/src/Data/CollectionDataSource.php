<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Data;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
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
    /** @var array<string, \Collator> one per locale, built on first use */
    private static array $collators = [];

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

    /**
     * @param  callable(Collection<int, mixed>): mixed  $callback
     */
    public function chunk(QueryPlan $plan, int $size, callable $callback): void
    {
        foreach ($this->apply($plan)->values()->chunk($size) as $batch) {
            if ($callback($batch->values()) === false) {
                return;
            }
        }
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

        // The term rides on the plan (QueryPlan::$searchTerm) because a source
        // has nothing else to go on; the clauses say which columns to look in.
        // Any column containing it keeps the row, case-insensitively — what the
        // Eloquent path's LIKE does for a plain term.
        $term = trim((string) $plan->searchTerm);

        if ($term !== '' && $plan->searchClauses !== []) {
            $needle = mb_strtolower($term);

            $rows = $rows->filter(function (array $row) use ($plan, $needle): bool {
                foreach ($plan->searchClauses as $clause) {
                    if (str_contains(mb_strtolower(self::text($row[$clause->column] ?? null)), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        if ($plan->sortClauses === []) {
            return $rows;
        }

        // One comparison across every clause, in order — what a multi-column
        // ORDER BY means — so the most significant clause decides and the next
        // only breaks its ties.
        return $rows->sort(function (array $a, array $b) use ($plan): int {
            foreach ($plan->sortClauses as $sort) {
                $order = self::compare($a[$sort->column] ?? null, $b[$sort->column] ?? null);

                if ($order !== 0) {
                    return $sort->direction === 'desc' ? -$order : $order;
                }
            }

            return 0;
        });
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
        $value = self::scalar($row[$filter->column] ?? null);
        $operand = $filter->value;

        // Operators arrive as the filters write them — TextFilter says `LIKE`,
        // NumberRangeFilter `BETWEEN` — so they are compared case-insensitively.
        return match (strtolower($filter->operator)) {
            '=' => $value == self::scalar($operand),
            '!=', '<>' => $value != self::scalar($operand),
            '>' => $value > $operand,
            '>=' => $value >= $operand,
            '<' => $value < $operand,
            '<=' => $value <= $operand,
            'like' => self::like($value, (string) $operand),
            'not like' => ! self::like($value, (string) $operand),
            'in' => is_array($operand) && in_array($value, array_map(self::scalar(...), $operand), false),
            'not in' => is_array($operand) && ! in_array($value, array_map(self::scalar(...), $operand), false),
            // Either bound may be missing: "at least 100" is [100, null].
            'between' => is_array($operand)
                && (($operand[0] ?? null) === null || $value >= $operand[0])
                && (($operand[1] ?? null) === null || $value <= $operand[1]),
            'is null' => $value === null,
            'is not null' => $value !== null,
            default => throw UnsupportedQueryAspectException::notDeclared(
                "filter operator [{$filter->operator}]",
                self::class,
            ),
        };
    }

    /**
     * SQL's LIKE over a PHP value: `%` is any run, `_` one character, and the
     * comparison ignores case, the way the default collations do.
     */
    private static function like(mixed $value, string $pattern): bool
    {
        if ($value === null) {
            return false;
        }

        $regex = '/^'.strtr(preg_quote($pattern, '/'), ['%' => '.*', '_' => '.']).'$/iu';

        return preg_match($regex, self::text($value)) === 1;
    }

    /** A backed enum compares by its value, the way it is stored. */
    /**
     * Order two values the way a database would: nothing first, numbers as
     * numbers, and text by the application's language rather than by bytes.
     *
     * Byte order puts every accented capital after `Z` — `Černý` after
     * `Veselý` — which no reader of Czech, German or French expects, and which
     * a table over a database never showed them. The `intl` Collator is the
     * real answer; without the extension, the letters are compared without
     * their accents, which puts `Č` among the `C`s.
     */
    private static function compare(mixed $a, mixed $b): int
    {
        $a = self::scalar($a);
        $b = self::scalar($b);

        if ($a === null || $b === null) {
            return ($a === null ? 0 : 1) <=> ($b === null ? 0 : 1);
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        if ((is_string($a) || $a instanceof \Stringable) && (is_string($b) || $b instanceof \Stringable)) {
            return self::collate((string) $a, (string) $b);
        }

        return $a <=> $b;
    }

    private static function collate(string $a, string $b): int
    {
        $collator = class_exists(\Collator::class) ? self::$collators[app()->getLocale()] ??= new \Collator(app()->getLocale()) : null;
        $order = $collator?->compare($a, $b);

        // No intl, or text the Collator refuses (it answers false for invalid
        // UTF-8): compare without accents, then byte for byte to stay total.
        if (is_int($order)) {
            return $order <=> 0;
        }

        return strnatcasecmp(Str::ascii($a), Str::ascii($b)) <=> 0 ?: strcmp($a, $b) <=> 0;
    }

    private static function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private static function text(mixed $value): string
    {
        $value = self::scalar($value);

        return match (true) {
            $value === null => '',
            $value instanceof \UnitEnum => $value->name,
            is_bool($value) => $value ? '1' : '0',
            is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
