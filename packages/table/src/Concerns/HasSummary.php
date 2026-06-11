<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Support\Trans;

/**
 * Trait HasSummary
 *
 * Adds aggregation/summary support to columns. Each column can define
 * one or more summary functions that appear in the table footer.
 *
 * Usage on Column:
 *
 *   Column::make('price')
 *       ->summarize('sum')                        // simple built-in
 *       ->summarize('avg', label: 'Průměr')       // with custom label
 *       ->summarize('count')                       // count non-null
 *       ->summarize('distinctCount')               // count distinct values
 *       ->summarize('min')                         // minimum
 *       ->summarize('max')                         // maximum
 *       ->summarize('range')                       // "min – max"
 *       ->summarize('median')                      // median value
 *       ->summarize('variance')                    // sample variance
 *       ->summarize('stddev')                      // sample standard deviation
 *       ->summarize('first')                       // first value
 *       ->summarize('last')                        // last value
 *       ->summarize(fn($values, $query) => ...)   // custom closure
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
     * Each entry: ['type' => string|Closure, 'label' => ?string, 'scope' => string,
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
     * @param  string|Closure  $type  Built-in type or custom callback(Collection $values, ?Builder $query): mixed
     * @param  string|null  $label  Display label (auto-generated if null)
     * @param  string  $scope  'page' (current page), 'query' (all filtered), 'selection', or 'subRows'
     * @param  Closure|null  $format  Optional formatter: fn(mixed $result): string
     * @param  Closure|null  $when  Optional filter: fn(Builder $query): Builder for DB scope,
     *                              or fn(mixed $value, mixed $record): bool for in-memory scopes.
     *                              Restricts which records are aggregated (e.g. only paid invoices).
     */
    public function summarize(
        string|Closure $type,
        ?string $label = null,
        string $scope = 'query',
        ?Closure $format = null,
        ?Closure $when = null,
    ): static {
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
        return $this->summarize('sum', $label ?? Trans::get('wire-table::messages.summary_sum'), $scope);
    }

    /**
     * Shortcut: Add an average summary.
     */
    public function summarizeAvg(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('avg', $label ?? Trans::get('wire-table::messages.summary_avg'), $scope);
    }

    /**
     * Shortcut: Add a count summary.
     */
    public function summarizeCount(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('count', $label ?? Trans::get('wire-table::messages.summary_count'), $scope);
    }

    /**
     * Shortcut: Add a min summary.
     */
    public function summarizeMin(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('min', $label ?? Trans::get('wire-table::messages.summary_min'), $scope);
    }

    /**
     * Shortcut: Add a max summary.
     */
    public function summarizeMax(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('max', $label ?? Trans::get('wire-table::messages.summary_max'), $scope);
    }

    /**
     * Shortcut: Add a range summary (min – max).
     */
    public function summarizeRange(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('range', $label ?? Trans::get('wire-table::messages.summary_range'), $scope);
    }

    /**
     * Shortcut: Add a count-of-distinct-values summary.
     */
    public function summarizeDistinct(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('distinctCount', $label ?? Trans::get('wire-table::messages.summary_distinct'), $scope);
    }

    /**
     * Shortcut: Add a median summary.
     */
    public function summarizeMedian(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('median', $label ?? Trans::get('wire-table::messages.summary_median'), $scope);
    }

    /**
     * Shortcut: Add a sample standard deviation summary.
     */
    public function summarizeStddev(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('stddev', $label ?? Trans::get('wire-table::messages.summary_stddev'), $scope);
    }

    public function hasSummary(): bool
    {
        return ! empty($this->summaries);
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
     * @return array<int, array<string, mixed>>
     */
    public function computeSummaries(Collection $pageRecords, ?Builder $query = null): array
    {
        $results = [];

        foreach ($this->summaries as $summary) {
            $type = $summary['type'];
            $label = $summary['label'];
            $scope = $summary['scope'];
            $format = $summary['format'];
            $when = $summary['when'] ?? null;

            $value = $this->computeSingleSummary($type, $scope, $pageRecords, $query, $when);

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
        string|Closure $type,
        string $scope,
        Collection $pageRecords,
        ?Builder $query,
        ?Closure $when = null,
    ): mixed {
        // Aggregate (rollup) columns expose their value as a computed attribute
        // (e.g. items_sum_total), not a real DB column. Summarizing one yields a
        // grand total of all sub-rows across the parent set — so route it through
        // the in-memory path using that attribute, even for 'query' scope.
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

        // Aggregate column at 'query' scope: load the filtered parent rows (the
        // query already has withSum/withCount applied) and aggregate the attribute.
        if ($scope === 'query' && $query !== null && $isAggregate) {
            $q = clone $query;

            if ($when !== null) {
                $q = $when($q) ?? $q;
            }

            $pageRecords = $q->get();
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
     * portable across drivers (median/variance/stddev/first/last) fall back to
     * plucking the column and computing in PHP.
     *
     * @param  Builder<Model>  $query
     */
    protected function computeQuerySummary(string $type, string $column, Builder $query): mixed
    {
        return match ($type) {
            'sum' => $query->sum($column),
            'avg' => $query->avg($column),
            'count' => (clone $query)->whereNotNull($column)->count(),
            'distinctCount' => (clone $query)->whereNotNull($column)->distinct()->count($column),
            'min' => $query->min($column),
            'max' => $query->max($column),
            'range' => $this->formatRange($query->min($column), $query->max($column)),
            'median', 'variance', 'stddev', 'first', 'last' => $this->computeCollectionSummary(
                $type,
                $query->pluck($column)->filter(fn ($v) => $v !== null)->values(),
            ),
            default => null,
        };
    }

    /**
     * Compute summary from in-memory collection.
     *
     * @param  Collection<int, mixed>  $values
     */
    protected function computeCollectionSummary(string $type, Collection $values): mixed
    {
        if ($values->isEmpty()) {
            return match ($type) {
                'sum' => 0,
                'count', 'distinctCount' => 0,
                'range' => '–',
                default => null,
            };
        }

        return match ($type) {
            'sum' => $values->sum(),
            'avg' => round((float) $values->avg(), 2),
            'count' => $values->count(),
            'distinctCount' => $values->unique()->count(),
            'min' => $values->min(),
            'max' => $values->max(),
            'range' => $this->formatRange($values->min(), $values->max()),
            'median' => $this->computeMedian($values),
            'variance' => round($this->computeVariance($values), 2),
            'stddev' => round(sqrt($this->computeVariance($values)), 2),
            'first' => $values->first(),
            'last' => $values->last(),
            default => null,
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
    protected function formatSummaryValue(string|Closure $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // 'range' already produces a formatted "min – max" string.
        if ($type === 'range') {
            return $value;
        }

        // Counts are integers — no decimals, but still allow prefix/suffix? No:
        // counts are not money, so leave them bare.
        if ($type === 'count' || $type === 'distinctCount') {
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
    protected function getDefaultSummaryLabel(string|Closure $type): string
    {
        if ($type instanceof Closure) {
            return Trans::get('wire-table::messages.summary_total');
        }

        return match ($type) {
            'sum' => Trans::get('wire-table::messages.summary_sum'),
            'avg' => Trans::get('wire-table::messages.summary_avg'),
            'count' => Trans::get('wire-table::messages.summary_count'),
            'distinctCount' => Trans::get('wire-table::messages.summary_distinct'),
            'min' => Trans::get('wire-table::messages.summary_min'),
            'max' => Trans::get('wire-table::messages.summary_max'),
            'range' => Trans::get('wire-table::messages.summary_range'),
            'median' => Trans::get('wire-table::messages.summary_median'),
            'variance' => Trans::get('wire-table::messages.summary_variance'),
            'stddev' => Trans::get('wire-table::messages.summary_stddev'),
            'first' => Trans::get('wire-table::messages.summary_first'),
            'last' => Trans::get('wire-table::messages.summary_last'),
            default => ucfirst($type),
        };
    }
}
