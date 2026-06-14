<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireTable\Columns\SummaryBatch;
use NyonCode\WireTable\Columns\SummaryType;

/**
 * Trait HasSummary
 *
 * Adds aggregation/summary support to columns. Each column can define
 * one or more summary functions that appear in the table footer.
 *
 * Usage on Column:
 *
 *   Column::make('price')
 *       ->summarize(SummaryType::Sum)              // enum case
 *       ->summarize('sum')                         // or its string value
 *       ->summarize('avg', label: 'Průměr')        // with custom label
 *       ->summarize(SummaryType::Median)           // see SummaryType for all types
 *       ->summarize(fn($values, $query) => ...)    // custom closure
 *
 * String types are normalized to SummaryType — unknown strings throw.
 *
 * Numeric summary results are formatted using the column's prefix/suffix and,
 * when set, ->summaryDecimals() — so SUM(price) shows "1 234,50 Kč", not 1234.5.
 *
 * Summaries can be computed over:
 *   - Visible page records ('page')
 *   - All records matching current filters ('query')
 *   - Selected records ('selection')
 *   - Sub-rows of a parent record ('subRows')
 */
trait HasSummary
{
    /**
     * Summary definitions.
     * Each entry: ['type' => SummaryType|Closure, 'label' => ?string, 'scope' => string,
     *              'format' => ?Closure, 'when' => ?Closure]
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $summaries = [];

    /**
     * Decimal places to use when formatting numeric summary results.
     * Null = leave the raw value untouched (only prefix/suffix applied).
     */
    protected ?int $summaryDecimals = null;

    /** Decimal separator for formatted numeric summaries. */
    protected string $summaryDecimalSeparator = '.';

    /** Thousands separator for formatted numeric summaries. */
    protected string $summaryThousandsSeparator = ' ';

    /**
     * Add a summary aggregation to this column.
     *
     * @param  string|Closure|SummaryType  $type  Built-in type (enum case or its string value)
     *                                            or custom callback(Collection $values, ?Builder $query): mixed
     * @param  string|null  $label  Display label (auto-generated if null)
     * @param  string  $scope  'page' (current page), 'query' (all filtered), 'selection', or 'subRows'
     * @param  Closure|null  $format  Optional formatter: fn(mixed $result): string
     * @param  Closure|null  $when  Optional filter: fn(Builder $query): Builder for DB scope,
     *                              or fn(mixed $value, mixed $record): bool for in-memory scopes.
     *                              Restricts which records are aggregated (e.g. only paid invoices).
     */
    public function summarize(
        string|Closure|SummaryType $type,
        ?string $label = null,
        string $scope = 'query',
        ?Closure $format = null,
        ?Closure $when = null,
    ): static {
        if (is_string($type)) {
            $type = SummaryType::tryFrom($type) ?? throw new InvalidArgumentException(
                "Unknown summary type [{$type}]. Valid types: ".
                implode(', ', array_column(SummaryType::cases(), 'value')).'.',
            );
        }

        $this->summaries[] = [
            'type' => $type,
            'label' => $label,
            'scope' => $scope,
            'format' => $format,
            'when' => $when,
        ];

        return $this;
    }

    /**
     * Set decimal formatting for numeric summary results on this column.
     *
     * Example: ->summaryDecimals(2) → 1234.5 renders as "1 234,50" (with separators).
     */
    public function summaryDecimals(int $decimals, string $decimalSeparator = ',', string $thousandsSeparator = ' '): static
    {
        $this->summaryDecimals = $decimals;
        $this->summaryDecimalSeparator = $decimalSeparator;
        $this->summaryThousandsSeparator = $thousandsSeparator;

        return $this;
    }

    /**
     * Shortcut: Add a sum summary.
     */
    public function summarizeSum(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Sum, $label, $scope);
    }

    /**
     * Shortcut: Add an average summary.
     */
    public function summarizeAvg(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Avg, $label, $scope);
    }

    /**
     * Shortcut: Add a count summary.
     */
    public function summarizeCount(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Count, $label, $scope);
    }

    /**
     * Shortcut: Add a min summary.
     */
    public function summarizeMin(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Min, $label, $scope);
    }

    /**
     * Shortcut: Add a max summary.
     */
    public function summarizeMax(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Max, $label, $scope);
    }

    /**
     * Shortcut: Add a range summary (min – max).
     */
    public function summarizeRange(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Range, $label, $scope);
    }

    /**
     * Shortcut: Add a count-of-distinct-values summary.
     */
    public function summarizeDistinct(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::DistinctCount, $label, $scope);
    }

    /**
     * Shortcut: Add a median summary.
     */
    public function summarizeMedian(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Median, $label, $scope);
    }

    /**
     * Shortcut: Add a sample standard deviation summary.
     */
    public function summarizeStddev(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Stddev, $label, $scope);
    }

    public function hasSummary(): bool
    {
        return ! empty($this->summaries);
    }

    /**
     * Whether the column has at least one summary declared with the given scope.
     */
    public function hasSummaryInScope(string $scope): bool
    {
        foreach ($this->summaries as $summary) {
            if ($summary['scope'] === $scope) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSummaries(): array
    {
        return $this->summaries;
    }

    /**
     * Compute all summaries for this column.
     *
     * @param  Collection<int, mixed>  $pageRecords  Records on current page
     * @param  Builder<Model>|null  $query  Full query (for 'query' scope)
     * @param  array<int, string>|null  $scopes  When given, only summaries declared
     *                                           with one of these scopes are computed.
     * @param  array<int, mixed>  $precomputed  Values already computed in a batched
     *                                          aggregate query, keyed by summary index
     *                                          (see SummaryBatch). Skips the per-summary
     *                                          query; formatting still applies here.
     * @return array<int, array<string, mixed>>
     */
    public function computeSummaries(Collection $pageRecords, ?Builder $query = null, ?array $scopes = null, array $precomputed = []): array
    {
        $results = [];

        foreach ($this->summaries as $index => $summary) {
            if ($scopes !== null && ! in_array($summary['scope'], $scopes, true)) {
                continue;
            }
            $type = $summary['type'];
            $label = $summary['label'];
            $scope = $summary['scope'];
            $format = $summary['format'];
            $when = $summary['when'] ?? null;

            $value = array_key_exists($index, $precomputed)
                ? $precomputed[$index]
                : $this->computeSingleSummary($type, $scope, $pageRecords, $query, $when);

            if ($format) {
                // Explicit formatter wins — full control to the caller.
                $value = call_user_func($format, $value);
            } else {
                // Default: apply the column's numeric formatting + prefix/suffix.
                $value = $this->formatSummaryValue($type, $value);
            }

            $results[] = [
                'label' => $label ?? $this->getDefaultSummaryLabel($type),
                'value' => $value,
            ];
        }

        return $results;
    }

    /**
     * Compute a single summary value.
     *
     * @param  Collection<int, mixed>  $pageRecords
     * @param  Builder<Model>|null  $query
     * @param  Closure|null  $when  Optional restriction (see summarize()).
     */
    protected function computeSingleSummary(
        SummaryType|Closure $type,
        string $scope,
        Collection $pageRecords,
        ?Builder $query,
        ?Closure $when = null,
    ): mixed {
        // Aggregate (rollup) columns expose their value as a computed attribute
        // (e.g. items_sum_total), not a real DB column. Summarizing one yields a
        // grand total of all sub-rows across the parent set; at 'query' scope it
        // is aggregated in SQL over a derived table, in-memory scopes read the
        // attribute from the already-loaded models.
        $isAggregate = $this->isAggregate();
        $columnName = $isAggregate ? ($this->getAggregateAttribute() ?? $this->getName()) : $this->getName();

        // For 'query' scope on a real column, use DB aggregation when possible.
        if ($scope === 'query' && $query !== null && ! $isAggregate) {
            $q = clone $query;

            if ($when !== null) {
                $q = $when($q) ?? $q;
            }

            if ($type instanceof Closure) {
                // Custom closure still gets the (restricted) values + query.
                $values = $q->pluck($columnName)->filter(fn ($v) => $v !== null);

                return call_user_func($type, $values, $q);
            }

            return $this->computeQuerySummary($type, $columnName, $q);
        }

        // Aggregate column at 'query' scope: the per-row rollup value is a
        // withSum/withCount subselect alias, so aggregate it in SQL over the
        // filtered query as a derived table — never load the rows into memory.
        if ($scope === 'query' && $query !== null && $isAggregate) {
            $q = clone $query;

            if ($when !== null) {
                $q = $when($q) ?? $q;
            }

            if ($type instanceof Closure) {
                $values = $this->wrapAggregateQuery($q)
                    ->pluck($columnName)
                    ->filter(fn ($v) => $v !== null)
                    ->values();

                return call_user_func($type, $values, $q);
            }

            return $this->computeAggregateQuerySummary($type, $columnName, $q);
        }

        // In-memory scopes ('page', 'selection', 'subRows') and aggregate columns:
        // pluck + optional filter.
        $values = $pageRecords
            ->when($when !== null, fn ($records) => $records->filter(
                fn ($record) => (bool) $when(data_get($record, $columnName), $record),
            ))
            ->pluck($columnName)
            ->filter(fn ($v) => $v !== null)
            ->values();

        if ($type instanceof Closure) {
            return call_user_func($type, $values, $query);
        }

        return $this->computeCollectionSummary($type, $values);
    }

    /**
     * Compute summary using DB aggregation (efficient for large datasets).
     *
     * Simple aggregates run natively in SQL. Statistical types that aren't
     * portable across drivers (SummaryType::isSqlNative() === false) fall back
     * to plucking the column and computing in PHP.
     *
     * @param  Builder<Model>  $query
     */
    protected function computeQuerySummary(SummaryType $type, string $column, Builder $query): mixed
    {
        if (! $type->isSqlNative()) {
            return $this->computeCollectionSummary(
                $type,
                $query->pluck($column)->filter(fn ($v) => $v !== null)->values(),
            );
        }

        return match ($type) {
            SummaryType::Sum => $query->sum($column),
            SummaryType::Avg => $query->avg($column),
            SummaryType::Count => (clone $query)->whereNotNull($column)->count(),
            SummaryType::DistinctCount => (clone $query)->whereNotNull($column)->distinct()->count($column),
            SummaryType::Min => $query->min($column),
            SummaryType::Max => $query->max($column),
            SummaryType::Range => $this->formatRange($query->min($column), $query->max($column)),
            default => null,
        };
    }

    /**
     * Wrap the filtered query as a derived table so the rollup alias
     * (e.g. items_sum_total) becomes addressable by outer SQL aggregates.
     *
     * @param  Builder<Model>  $query
     */
    protected function wrapAggregateQuery(Builder $query): \Illuminate\Database\Query\Builder
    {
        return $query->toBase()
            ->newQuery()
            ->fromSub($query->toBase(), 'wire_table_summary');
    }

    /**
     * Compute a summary over a rollup (withSum/withCount/…) alias in SQL.
     *
     * Mirrors computeQuerySummary(), but aggregates the derived-table column
     * instead of a real table column. Statistical types fall back to plucking
     * the alias and computing in PHP, exactly like real columns do.
     *
     * @param  Builder<Model>  $query
     */
    protected function computeAggregateQuerySummary(SummaryType $type, string $column, Builder $query): mixed
    {
        $wrapped = $this->wrapAggregateQuery($query);

        if (! $type->isSqlNative()) {
            return $this->computeCollectionSummary(
                $type,
                (clone $wrapped)->pluck($column)->filter(fn ($v) => $v !== null)->values(),
            );
        }

        return match ($type) {
            SummaryType::Sum => (clone $wrapped)->sum($column),
            SummaryType::Avg => ($avg = (clone $wrapped)->avg($column)) !== null ? round((float) $avg, 2) : null,
            SummaryType::Count => (clone $wrapped)->whereNotNull($column)->count(),
            SummaryType::DistinctCount => (clone $wrapped)->whereNotNull($column)->distinct()->count($column),
            SummaryType::Min => (clone $wrapped)->min($column),
            SummaryType::Max => (clone $wrapped)->max($column),
            SummaryType::Range => $this->computeAggregateRange($wrapped, $column),
            default => null,
        };
    }

    protected function computeAggregateRange(\Illuminate\Database\Query\Builder $wrapped, string $column): string
    {
        $min = (clone $wrapped)->min($column);
        $max = (clone $wrapped)->max($column);

        // Match the in-memory empty-set placeholder.
        if ($min === null && $max === null) {
            return '–';
        }

        return $this->formatRange($min, $max);
    }

    /**
     * Normalize a raw batched SQL aggregate so the result matches what the
     * per-summary query path returns for the same summary:
     *
     *  - Sum: Builder::sum() coalesces an empty set to 0 (`?: 0`)
     *  - Avg on rollup columns: computeAggregateQuerySummary() rounds to 2
     *  - Count/DistinctCount: Builder::count() casts to int
     *  - Range: formatted "min – max" string ('–' for an empty rollup set)
     *
     * @internal Used by {@see SummaryBatch}.
     */
    public function normalizeBatchedSummaryValue(SummaryType $type, mixed $raw): mixed
    {
        $isAggregate = $this->isAggregate();

        return match ($type) {
            SummaryType::Sum => $raw ?: 0,
            SummaryType::Avg => $isAggregate
                ? ($raw !== null ? round((float) $raw, 2) : null)
                : $raw,
            SummaryType::Count, SummaryType::DistinctCount => (int) $raw,
            SummaryType::Range => ($isAggregate && $raw['min'] === null && $raw['max'] === null)
                ? '–'
                : $this->formatRange($raw['min'], $raw['max']),
            default => $raw,
        };
    }

    /**
     * Compute summary from in-memory collection.
     *
     * @param  Collection<int, mixed>  $values
     */
    protected function computeCollectionSummary(SummaryType $type, Collection $values): mixed
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
            SummaryType::Range => $this->formatRange($values->min(), $values->max()),
            SummaryType::Median => $this->computeMedian($values),
            SummaryType::Variance => round($this->computeVariance($values), 2),
            SummaryType::Stddev => round(sqrt($this->computeVariance($values)), 2),
            SummaryType::First => $values->first(),
            SummaryType::Last => $values->last(),
        };
    }

    /**
     * Median of a numeric collection.
     *
     * @param  Collection<int, mixed>  $values
     */
    protected function computeMedian(Collection $values): float
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
     * Sample variance of a numeric collection (n − 1 denominator).
     * Returns 0.0 for a single value.
     *
     * @param  Collection<int, mixed>  $values
     */
    protected function computeVariance(Collection $values): float
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

    protected function formatRange(mixed $min, mixed $max): string
    {
        return $this->formatNumeric($min).' – '.$this->formatNumeric($max);
    }

    /**
     * Apply the column's numeric formatting (decimals + prefix/suffix) to a
     * summary result. Non-numeric and already-formatted string results
     * (range, first/last text) only receive prefix/suffix when numeric.
     */
    protected function formatSummaryValue(SummaryType|Closure $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // 'range' already produces a formatted "min – max" string.
        if ($type === SummaryType::Range) {
            return $value;
        }

        // Counts are integers — no decimals, but still allow prefix/suffix? No:
        // counts are not money, so leave them bare.
        if ($type instanceof SummaryType && $type->isCount()) {
            return $value;
        }

        if (! is_numeric($value)) {
            return $value;
        }

        $hasDecorations = ($this->prefix ?? null) !== null || ($this->suffix ?? null) !== null;

        // Nothing to apply — preserve the raw numeric value (int/float) untouched.
        if ($this->summaryDecimals === null && ! $hasDecorations) {
            return $value;
        }

        return $this->decorateNumeric($this->formatNumeric($value));
    }

    /**
     * Format a numeric value using the configured decimals/separators.
     */
    protected function formatNumeric(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) $value;
        }

        if ($this->summaryDecimals === null) {
            return (string) $value;
        }

        return number_format(
            (float) $value,
            $this->summaryDecimals,
            $this->summaryDecimalSeparator,
            $this->summaryThousandsSeparator,
        );
    }

    /**
     * Wrap a formatted numeric string in the column's prefix/suffix.
     */
    protected function decorateNumeric(string $formatted): string
    {
        if ($this->prefix !== null) {
            $formatted = $this->prefix.$formatted;
        }

        if ($this->suffix !== null) {
            $formatted = $formatted.$this->suffix;
        }

        return $formatted;
    }

    /**
     * Default label for built-in summary types.
     */
    protected function getDefaultSummaryLabel(SummaryType|Closure $type): string
    {
        if ($type instanceof Closure) {
            return Trans::get('wire-table::messages.summary_total');
        }

        return $type->label();
    }
}
