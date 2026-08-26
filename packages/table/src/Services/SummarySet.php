<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Columns\Column;

/**
 * The summaries of a set of columns over a set of rows, as one map.
 *
 * The layer that was missing between the two that already exist:
 * {@see SummaryCalculator} computes one value, {@see SummaryBatch} folds the
 * SQL-native ones into two queries, and `Support\SummaryRenderer` renders the
 * result — but nothing owned "run these columns over these rows". So
 * `WithTable` did, in a 54-line method that had to repeat itself for the
 * sub-row branch.
 *
 * What stays with the caller is which rows a scope *means*: the page, the
 * selection, the whole filtered query, a parent's children. Only the host knows
 * that. What arrives here is the answer.
 */
final class SummarySet
{
    public function __construct(private readonly SummaryBatch $batch) {}

    /**
     * Summaries keyed by column name, for every column that declares one.
     *
     * Pass `$query` when the scope is the whole filtered set rather than rows
     * already in memory: it is what lets the SQL-native aggregates be batched
     * into two queries instead of one per summary per column on every render.
     * Without it — a page, a selection, a parent's children — the values are
     * computed from the collection, which is the only thing there is.
     *
     * @param  array<int, Column>  $columns
     * @param  Collection<int, Model>  $records
     * @param  Builder<Model>|null  $query
     * @return array<string, array<int, mixed>>
     */
    public function build(array $columns, Collection $records, ?Builder $query = null): array
    {
        $batched = $query !== null ? $this->batch->compute($columns, $query) : [];

        $summaries = [];

        foreach ($columns as $column) {
            if (! $column->hasSummary()) {
                continue;
            }

            $summaries[$column->getName()] = $column->computeSummaries(
                $records,
                $query,
                null,
                $batched[$column->getName()] ?? [],
            );
        }

        return $summaries;
    }
}
