<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Services\SummaryBatch;
use NyonCode\WireTable\Services\SummaryCalculator;
use NyonCode\WireTable\Services\SummaryFormatter;
use NyonCode\WireTable\Support\SummaryFormat;
use NyonCode\WireTable\Support\SummaryTarget;

/**
 * Being summarized in the table footer.
 *
 * Configuration and the fluent API only: the aggregation lives in
 * {@see SummaryCalculator} and the rendering in {@see SummaryFormatter}. It used
 * to be one 616-line trait holding SQL aggregation, derived-table wrapping and
 * sample statistics — inherited by all 13 column types, and untestable without
 * a column.
 *
 * Each column can define one or more summary functions that appear in the
 * table footer.
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
trait CanBeSummarized
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
            $type = SummaryType::tryFrom($type) ?? throw TableConfigurationException::unknownSummaryType(
                $type,
                array_column(SummaryType::cases(), 'value'),
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

    // Shortcuts for the built-in types; see SummaryType for what each one means.

    /** Shortcut: add a column footer summary of the SUM (`scope`: `query` all rows, or `page`). */
    public function summarizeSum(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Sum, $label, $scope);
    }

    /** Shortcut: add a column footer summary of the AVERAGE. */
    public function summarizeAvg(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Avg, $label, $scope);
    }

    /** Shortcut: add a column footer summary of the row COUNT. */
    public function summarizeCount(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Count, $label, $scope);
    }

    /** Shortcut: add a column footer summary of the MINIMUM. */
    public function summarizeMin(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize(SummaryType::Min, $label, $scope);
    }

    /** Shortcut: add a column footer summary of the MAXIMUM. */
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

    /** Shortcut: add a column footer summary of the MEDIAN. */
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
     * Compute this column's summaries.
     *
     * @param  Collection<int, mixed>  $pageRecords
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
        $calculator = app(SummaryCalculator::class);
        $formatter = app(SummaryFormatter::class);
        $target = $this->getSummaryTarget();
        $results = [];

        foreach ($this->summaries as $index => $summary) {
            if ($scopes !== null && ! in_array($summary['scope'], $scopes, true)) {
                continue;
            }

            $type = $summary['type'];

            $value = array_key_exists($index, $precomputed)
                ? $precomputed[$index]
                : $calculator->compute(
                    $type,
                    $summary['scope'],
                    $target,
                    $pageRecords,
                    $query,
                    $summary['when'] ?? null,
                );

            // An explicit formatter wins — full control to the caller.
            $value = $summary['format']
                ? call_user_func($summary['format'], $value)
                : $formatter->format($type, $value, $target->format);

            $results[] = [
                'label' => $summary['label'] ?? $formatter->defaultLabel($type),
                'value' => $value,
            ];
        }

        return $results;
    }

    /**
     * Normalize a raw batched SQL aggregate to match the per-summary query path.
     *
     * @internal Used by {@see SummaryBatch}.
     */
    public function normalizeBatchedSummaryValue(SummaryType $type, mixed $raw): mixed
    {
        return app(SummaryCalculator::class)->normalizeBatched($type, $raw, $this->getSummaryTarget());
    }

    /**
     * What the calculator and formatter need to know about this column.
     *
     * A rollup column summarizes its computed attribute (e.g. items_sum_total),
     * not a real table column — see {@see SummaryTarget}.
     */
    protected function getSummaryTarget(): SummaryTarget
    {
        $isAggregate = $this->isAggregate();

        return new SummaryTarget(
            column: $isAggregate ? ($this->getAggregateAttribute() ?? $this->getName()) : $this->getName(),
            isAggregate: $isAggregate,
            format: new SummaryFormat(
                decimals: $this->summaryDecimals,
                decimalSeparator: $this->summaryDecimalSeparator,
                thousandsSeparator: $this->summaryThousandsSeparator,
                prefix: $this->prefix ?? null,
                suffix: $this->suffix ?? null,
            ),
        );
    }
}
