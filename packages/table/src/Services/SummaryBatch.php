<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\SummaryType;

/**
 * Batches SQL-native, query-scoped column summaries into a single aggregate
 * query instead of one query per summary per column.
 *
 * A table footer with several summarized columns previously ran one SQL
 * aggregate per summary (SUM, AVG, COUNT, …) on every Livewire render. This
 * collects all batchable summaries and executes at most two queries:
 *
 *  1. plain columns      — aggregates over the filtered base query
 *  2. rollup columns     — aggregates over the query wrapped as a derived
 *                          table, so withSum/withCount aliases are addressable
 *
 * Summaries that cannot be expressed this way fall back to the existing
 * per-summary path in {@see SummaryCalculator::compute()}: closure
 * types, non-SQL-native types (median, stddev, …), summaries with a when()
 * restriction, and dot-notation relation columns.
 *
 * Raw values are normalized via Column::normalizeBatchedSummaryValue() so the
 * results are byte-for-byte identical to the per-summary query path.
 */
final class SummaryBatch
{
    public function __construct(private readonly SummaryCalculator $calculator) {}

    /**
     * Compute batchable summaries for the given columns.
     *
     * @param  array<int, Column>  $columns
     * @param  Builder<Model>  $query  The filtered table query (not mutated)
     * @param  array<int, string>  $scopes  Summary scopes eligible for batching
     * @return array<string, array<int, mixed>> [columnName => [summaryIndex => value]]
     */
    public function compute(array $columns, Builder $query, array $scopes = ['query']): array
    {
        $grammar = $query->getQuery()->getGrammar();

        // Each entry: ['column' => Column, 'index' => int, 'type' => SummaryType,
        //              'aliases' => array<int, string>]
        $plainSelects = [];
        $plainTargets = [];
        $rollupSelects = [];
        $rollupTargets = [];

        foreach ($columns as $column) {
            if (! $column->hasSummary()) {
                continue;
            }

            $isAggregate = $column->isAggregate();
            $columnName = $isAggregate
                ? ($column->getAggregateAttribute() ?? $column->getName())
                : $column->getName();

            // Relation-path columns can't be aggregated on the base table.
            if (str_contains($columnName, '.')) {
                continue;
            }

            $wrapped = $grammar->wrap($columnName);

            foreach ($column->getSummaries() as $index => $summary) {
                if (! in_array($summary['scope'], $scopes, true)) {
                    continue;
                }

                // when() restrictions mutate the query per summary — not batchable.
                if (($summary['when'] ?? null) !== null) {
                    continue;
                }

                $type = $summary['type'];

                if (! $type instanceof SummaryType || ! $type->isSqlNative()) {
                    continue;
                }

                $selects = $isAggregate ? $rollupSelects : $plainSelects;
                $aliasBase = 'wt_agg_'.count($selects);

                if ($type === SummaryType::Range) {
                    $entry = [
                        $aliasBase.'_min' => "MIN({$wrapped})",
                        $aliasBase.'_max' => "MAX({$wrapped})",
                    ];
                    $aliases = array_keys($entry);
                } else {
                    $expr = match ($type) {
                        SummaryType::Sum => "SUM({$wrapped})",
                        SummaryType::Avg => "AVG({$wrapped})",
                        SummaryType::Count => "COUNT({$wrapped})",
                        SummaryType::DistinctCount => "COUNT(DISTINCT {$wrapped})",
                        SummaryType::Min => "MIN({$wrapped})",
                        SummaryType::Max => "MAX({$wrapped})",
                        default => null,
                    };

                    if ($expr === null) {
                        continue;
                    }

                    $entry = [$aliasBase => $expr];
                    $aliases = [$aliasBase];
                }

                $target = [
                    'column' => $column,
                    'index' => $index,
                    'type' => $type,
                    'aliases' => $aliases,
                ];

                if ($isAggregate) {
                    $rollupSelects += $entry;
                    $rollupTargets[] = $target;
                } else {
                    $plainSelects += $entry;
                    $plainTargets[] = $target;
                }
            }
        }

        $results = [];

        if ($plainSelects !== []) {
            $row = $this->runPlain($query, $plainSelects);
            $this->collect($results, $plainTargets, $row);
        }

        if ($rollupSelects !== []) {
            $row = $this->runRollup($query, $rollupSelects);
            $this->collect($results, $rollupTargets, $row);
        }

        return $results;
    }

    /**
     * Run the aggregates over the filtered base query.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, string>  $selects  alias => expression
     */
    private function runPlain(Builder $query, array $selects): ?object
    {
        $base = (clone $query)->toBase();
        $base->reorder();
        $base->select([]);
        $base->selectRaw($this->compileSelects($selects));

        return $base->first();
    }

    /**
     * Run the aggregates over the query wrapped as a derived table, so rollup
     * aliases (e.g. items_sum_total) become addressable. The wrapping itself is
     * {@see SummaryCalculator::wrap()}'s — this used to rebuild it by hand.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, string>  $selects  alias => expression
     */
    private function runRollup(Builder $query, array $selects): ?object
    {
        return $this->calculator->wrap($query)
            ->selectRaw($this->compileSelects($selects))
            ->first();
    }

    /**
     * @param  array<string, string>  $selects  alias => expression
     */
    private function compileSelects(array $selects): string
    {
        $parts = [];

        foreach ($selects as $alias => $expr) {
            $parts[] = "{$expr} as {$alias}";
        }

        return implode(', ', $parts);
    }

    /**
     * Map the aggregate row back onto [columnName => [index => normalized value]].
     *
     * @param  array<string, array<int, mixed>>  $results
     * @param  array<int, array{column: Column, index: int, type: SummaryType, aliases: array<int, string>}>  $targets
     */
    private function collect(array &$results, array $targets, ?object $row): void
    {
        foreach ($targets as $target) {
            /** @var Column $column */
            $column = $target['column'];
            $type = $target['type'];

            if ($type === SummaryType::Range) {
                [$minAlias, $maxAlias] = $target['aliases'];
                $raw = [
                    'min' => $row?->{$minAlias},
                    'max' => $row?->{$maxAlias},
                ];
            } else {
                $raw = $row?->{$target['aliases'][0]};
            }

            $results[$column->getName()][$target['index']] = $column->normalizeBatchedSummaryValue($type, $raw);
        }
    }
}
