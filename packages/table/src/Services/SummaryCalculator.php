<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Support\SummaryTarget;

/**
 * Computes one summary value — in SQL where it can, in PHP where it must.
 *
 * Extracted from the HasSummary trait, which mixed SQL aggregation, derived-table
 * wrapping and sample statistics into every one of the 13 column types. Holds no
 * column: what it needs arrives as a {@see SummaryTarget}, so median and variance
 * can be tested without a database.
 *
 * The formatter is a dependency because one summary type, Range, produces a
 * rendered "min – max" string rather than a number — the one place computing and
 * formatting genuinely meet.
 */
final class SummaryCalculator
{
    public function __construct(private readonly SummaryFormatter $formatter) {}

    /**
     * @param  Collection<int, mixed>  $pageRecords
     * @param  Builder<Model>|null  $query
     * @param  Closure|null  $when  Optional restriction (see Column::summarize()).
     */
    public function compute(
        SummaryType|Closure $type,
        string $scope,
        SummaryTarget $target,
        Collection $pageRecords,
        ?Builder $query = null,
        ?Closure $when = null,
    ): mixed {
        // A real column at 'query' scope aggregates natively in SQL.
        if ($scope === 'query' && $query !== null && ! $target->isAggregate) {
            $q = $this->restrict($query, $when);

            if ($type instanceof Closure) {
                // Deliberately not ->values(): this is the one path that hands a
                // user closure a key-preserving collection, and reindexing it
                // would change what their callback sees.
                return call_user_func($type, $q->pluck($target->column)->filter(fn ($v) => $v !== null), $q);
            }

            return $this->fromQuery($type, $target, $q);
        }

        // A rollup column at 'query' scope: its value is a subselect alias, so it
        // is aggregated over a derived table — never by loading the rows.
        if ($scope === 'query' && $query !== null && $target->isAggregate) {
            $q = $this->restrict($query, $when);

            if ($type instanceof Closure) {
                $values = $this->wrap($q)->pluck($target->column)->filter(fn ($v) => $v !== null)->values();

                return call_user_func($type, $values, $q);
            }

            return $this->fromAggregateQuery($type, $target, $q);
        }

        // In-memory scopes: 'page', 'selection', 'subRows'.
        $values = $pageRecords
            ->when($when !== null, fn ($records) => $records->filter(
                fn ($record) => (bool) $when(data_get($record, $target->column), $record),
            ))
            ->pluck($target->column)
            ->filter(fn ($v) => $v !== null)
            ->values();

        if ($type instanceof Closure) {
            return call_user_func($type, $values, $query);
        }

        return $this->fromCollection($type, $values, $target);
    }

    /**
     * Summarize an in-memory collection.
     *
     * @param  Collection<int, mixed>  $values
     */
    public function fromCollection(SummaryType $type, Collection $values, SummaryTarget $target): mixed
    {
        if ($values->isEmpty()) {
            return $type->emptyValue();
        }

        return match ($type) {
            SummaryType::Sum => $values->sum(),
            SummaryType::Avg => round((float) $values->avg(), 2),
            SummaryType::Count => $values->count(),
            SummaryType::DistinctCount => $values->unique()->count(),
            SummaryType::Min => $values->min(),
            SummaryType::Max => $values->max(),
            SummaryType::Range => $this->formatter->range($values->min(), $values->max(), $target->format),
            SummaryType::Median => $this->median($values),
            SummaryType::Variance => round($this->variance($values), 2),
            SummaryType::Stddev => round(sqrt($this->variance($values)), 2),
            SummaryType::First => $values->first(),
            SummaryType::Last => $values->last(),
        };
    }

    /**
     * Median of a numeric collection.
     *
     * @param  Collection<int, mixed>  $values
     */
    public function median(Collection $values): float
    {
        $sorted = $values->map(fn ($v) => (float) $v)->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle - 1] + $sorted[$middle]) / 2;
        }

        return (float) $sorted[$middle];
    }

    /**
     * Sample variance (n − 1 denominator). Zero for a single value.
     *
     * @param  Collection<int, mixed>  $values
     */
    public function variance(Collection $values): float
    {
        $count = $values->count();

        if ($count < 2) {
            return 0.0;
        }

        $mean = (float) $values->avg();
        $sumSquares = $values->reduce(
            fn (float $carry, $v) => $carry + (((float) $v - $mean) ** 2),
            0.0,
        );

        return $sumSquares / ($count - 1);
    }

    /**
     * Wrap a filtered query as a derived table so a rollup alias (e.g.
     * items_sum_total) becomes addressable by outer SQL aggregates.
     *
     * @param  Builder<Model>  $query
     */
    public function wrap(Builder $query): QueryBuilder
    {
        return $query->toBase()
            ->newQuery()
            ->fromSub($query->toBase(), 'wire_table_summary');
    }

    /**
     * Normalize a raw batched SQL aggregate so it matches what the per-summary
     * query path returns for the same summary:
     *
     *  - Sum: Builder::sum() coalesces an empty set to 0
     *  - Avg on rollup columns: fromAggregateQuery() rounds to 2
     *  - Count/DistinctCount: Builder::count() casts to int
     *  - Range: a formatted "min – max" ('–' for an empty rollup set)
     *
     * @internal Used by {@see SummaryBatch}.
     */
    public function normalizeBatched(SummaryType $type, mixed $raw, SummaryTarget $target): mixed
    {
        return match ($type) {
            SummaryType::Sum => $raw ?: 0,
            SummaryType::Avg => $target->isAggregate
                ? ($raw !== null ? round((float) $raw, 2) : null)
                : $raw,
            SummaryType::Count, SummaryType::DistinctCount => (int) $raw,
            SummaryType::Range => ($target->isAggregate && $raw['min'] === null && $raw['max'] === null)
                ? '–'
                : $this->formatter->range($raw['min'], $raw['max'], $target->format),
            default => $raw,
        };
    }

    /**
     * Aggregate a real column in SQL. Types that are not portable across drivers
     * (SummaryType::isSqlNative() === false) fall back to PHP.
     *
     * @param  Builder<Model>  $query
     */
    private function fromQuery(SummaryType $type, SummaryTarget $target, Builder $query): mixed
    {
        $column = $target->column;

        // Qualify against the base table: a relation sort adds a LEFT JOIN, and a
        // bare column shared by both tables (e.g. `id`) makes the aggregate
        // ambiguous. Relation-path targets keep their dotted form untouched.
        $column = str_contains($column, '.') ? $column : $query->qualifyColumn($column);

        if (! $type->isSqlNative()) {
            return $this->fromCollection($type, $this->pluck($query, $column), $target);
        }

        return match ($type) {
            SummaryType::Sum => $query->sum($column),
            SummaryType::Avg => $query->avg($column),
            SummaryType::Count => (clone $query)->whereNotNull($column)->count(),
            SummaryType::DistinctCount => (clone $query)->whereNotNull($column)->distinct()->count($column),
            SummaryType::Min => $query->min($column),
            SummaryType::Max => $query->max($column),
            SummaryType::Range => $this->formatter->range($query->min($column), $query->max($column), $target->format),
            default => null,
        };
    }

    /**
     * Mirrors fromQuery() over a derived table, so a rollup alias can be
     * aggregated by outer SQL.
     *
     * @param  Builder<Model>  $query
     */
    private function fromAggregateQuery(SummaryType $type, SummaryTarget $target, Builder $query): mixed
    {
        $wrapped = $this->wrap($query);
        $column = $target->column;

        if (! $type->isSqlNative()) {
            $values = (clone $wrapped)->pluck($column)->filter(fn ($v) => $v !== null)->values();

            return $this->fromCollection($type, $values, $target);
        }

        return match ($type) {
            SummaryType::Sum => (clone $wrapped)->sum($column),
            SummaryType::Avg => ($avg = (clone $wrapped)->avg($column)) !== null ? round((float) $avg, 2) : null,
            SummaryType::Count => (clone $wrapped)->whereNotNull($column)->count(),
            SummaryType::DistinctCount => (clone $wrapped)->whereNotNull($column)->distinct()->count($column),
            SummaryType::Min => (clone $wrapped)->min($column),
            SummaryType::Max => (clone $wrapped)->max($column),
            SummaryType::Range => $this->aggregateRange($wrapped, $target),
            default => null,
        };
    }

    private function aggregateRange(QueryBuilder $wrapped, SummaryTarget $target): string
    {
        $min = (clone $wrapped)->min($target->column);
        $max = (clone $wrapped)->max($target->column);

        // Match the in-memory empty-set placeholder.
        if ($min === null && $max === null) {
            return '–';
        }

        return $this->formatter->range($min, $max, $target->format);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Collection<int, mixed>
     */
    private function pluck(Builder $query, string $column): Collection
    {
        return $query->pluck($column)->filter(fn ($v) => $v !== null)->values();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function restrict(Builder $query, ?Closure $when): Builder
    {
        $q = clone $query;

        if ($when !== null) {
            $q = $when($q) ?? $q;
        }

        return $q;
    }
}
