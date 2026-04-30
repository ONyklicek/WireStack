<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
        return $this->summarize('sum', $label ?? 'Součet', $scope);
    }

    /**
     * Shortcut: Add an average summary.
     */
    public function summarizeAvg(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('avg', $label ?? 'Průměr', $scope);
    }

    /**
     * Shortcut: Add a count summary.
     */
    public function summarizeCount(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('count', $label ?? 'Počet', $scope);
    }

    /**
     * Shortcut: Add a min summary.
     */
    public function summarizeMin(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('min', $label ?? 'Min', $scope);
    }

    /**
     * Shortcut: Add a max summary.
     */
    public function summarizeMax(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('max', $label ?? 'Max', $scope);
    }

    /**
     * Shortcut: Add a range summary (min – max).
     */
    public function summarizeRange(?string $label = null, string $scope = 'query'): static
    {
        return $this->summarize('range', $label ?? 'Rozsah', $scope);
    }

    public function hasSummary(): bool
    {
        return ! empty($this->summaries);
    }

    public function getSummaries(): array
    {
        return $this->summaries;
    }

    /**
     * Compute all summaries for this column.
     *
     * @param  Collection  $pageRecords  Records on current page
     * @param  Builder|null  $query  Full query (for 'query' scope)
     * @return array [['label' => string, 'value' => mixed], ...]
     */
    public function computeSummaries($pageRecords, $query = null): array
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
     */
    protected function computeSingleSummary(
        string|Closure $type,
        string $scope,
        $pageRecords,
        $query,
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
     */
    protected function computeQuerySummary(string $type, string $column, $query): mixed
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
     */
    protected function computeCollectionSummary(string $type, $values): mixed
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
            return 'Celkem';
        }

        return match ($type) {
            'sum' => 'Součet',
            'avg' => 'Průměr',
            'count' => 'Počet',
            'min' => 'Min',
            'max' => 'Max',
            'range' => 'Rozsah',
            default => ucfirst($type),
        };
    }
}
