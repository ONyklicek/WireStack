<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

use Illuminate\Support\Collection;

/**
 * A host that can answer for a table's totals.
 *
 * Wider than the other two host contracts, and deliberately so: these seven
 * methods are one capability rather than seven, because a host that can answer
 * any of them can answer all of them — they are the same computation asked at
 * different scopes, plus the two predicates that say whether to ask at all.
 * Splitting them would mean a renderer asking four interfaces the same question.
 *
 * The predicates come first for a reason. `SummaryRenderer` calls them before
 * computing anything, so a table with no summaries never runs a scope
 * resolution, and a table with no grouping never resolves a group.
 */
interface SummarisesTable
{
    /** Whether any column declares a summary at all. */
    public function tableHasSummaries(): bool;

    /** Whether the table groups *and* the grouping carries subtotals. */
    public function tableHasGroupSummaries(): bool;

    /**
     * @param  Collection<int, mixed>|null  $subRecords
     * @return array<string, array<int, mixed>>
     */
    public function computeTableSummaries(string $scope = 'query', mixed $parentRecord = null, ?Collection $subRecords = null): array;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function computeGroupSummaries(mixed $groupValue): array;

    /**
     * @return array<string, mixed>
     */
    public function computeSubRowGrandTotals(string $scope = 'query'): array;

    /** The active footer scope: 'page', 'query' or 'selection'. */
    public function getSummaryScope(): string;

    /**
     * @return array<string, string>
     */
    public function getSummaryScopeOptions(): array;
}
