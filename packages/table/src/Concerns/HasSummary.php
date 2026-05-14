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
 *       ->summarize('min')                         // minimum
 *       ->summarize('max')                         // maximum
 *       ->summarize('range')                       // "min – max"
 *       ->summarize(fn($values, $query) => ...)   // custom closure
 *
 * Summaries can be computed over:
 *   - Visible page records ('page')
 *   - All records matching current filters ('query')
 *   - Sub-rows of a parent record ('subRows')
 */
trait HasSummary
{
    /**
     * Summary definitions.
     * Each entry: ['type' => string|Closure, 'label' => ?string, 'scope' => string, 'format' => ?Closure]
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $summaries = [];

    /**
     * Add a summary aggregation to this column.
     *
     * @param  string|Closure  $type  Built-in type or custom callback(Collection $values, ?Builder $query): mixed
     * @param  string|null  $label  Display label (auto-generated if null)
     * @param  string  $scope  'page' (current page), 'query' (all filtered), or 'subRows'
     * @param  Closure|null  $format  Optional formatter: fn(mixed $result): string
     */
    public function summarize(
        string|Closure $type,
        ?string $label = null,
        string $scope = 'query',
        ?Closure $format = null,
    ): static {
        $this->summaries[] = [
            'type' => $type,
            'label' => $label,
            'scope' => $scope,
            'format' => $format,
        ];

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

            $value = $this->computeSingleSummary($type, $scope, $pageRecords, $query);

            if ($format) {
                $value = call_user_func($format, $value);
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
     */
    protected function computeSingleSummary(
        string|Closure $type,
        string $scope,
        Collection $pageRecords,
        ?Builder $query,
    ): mixed {
        $columnName = $this->getName();

        // Custom callback
        if ($type instanceof Closure) {
            $values = $pageRecords->pluck($columnName)->filter(fn ($v) => $v !== null);

            return call_user_func($type, $values, $query);
        }

        // For 'query' scope, use DB aggregation when possible
        if ($scope === 'query' && $query !== null) {
            return $this->computeQuerySummary($type, $columnName, $query);
        }

        // For 'page' scope, compute from in-memory collection
        $values = $pageRecords->pluck($columnName)->filter(fn ($v) => $v !== null);

        return $this->computeCollectionSummary($type, $values);
    }

    /**
     * Compute summary using DB aggregation (efficient for large datasets).
     *
     * @param  Builder<Model>  $query
     */
    protected function computeQuerySummary(string $type, string $column, Builder $query): mixed
    {
        // Clone to not affect original query
        $q = clone $query;

        return match ($type) {
            'sum' => $q->sum($column),
            'avg' => $q->avg($column),
            'count' => $q->whereNotNull($column)->count(),
            'min' => $q->min($column),
            'max' => $q->max($column),
            'range' => $q->min($column).' – '.$q->max($column),
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
                'count' => 0,
                'range' => '–',
                default => null,
            };
        }

        return match ($type) {
            'sum' => $values->sum(),
            'avg' => round($values->avg(), 2),
            'count' => $values->count(),
            'min' => $values->min(),
            'max' => $values->max(),
            'range' => $values->min().' – '.$values->max(),
            default => null,
        };
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
            'min' => Trans::get('wire-table::messages.summary_min'),
            'max' => Trans::get('wire-table::messages.summary_max'),
            'range' => Trans::get('wire-table::messages.summary_range'),
            default => ucfirst($type),
        };
    }
}
