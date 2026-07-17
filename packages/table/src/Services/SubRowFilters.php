<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation as EloquentRelation;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Table;

/**
 * Which filters constrain a table's sub-rows, and how.
 *
 * Two separate things narrow children, and they are easy to confuse:
 *
 *  - **scoped main filters** — a main-table {@see Filter::subRows()} constrains
 *    the displayed children the same way it constrained their parents;
 *  - **the interactive sub-row bar** — per-column filters the user types under
 *    an expanded row.
 *
 * Takes the table and the raw state arrays, so the rules can be exercised
 * without a Livewire host.
 */
final class SubRowFilters
{
    /**
     * Active scoped main filters paired with their values.
     * Empty when sub-rows are not relation-backed.
     *
     * @param  array<string, mixed>  $filterValues  the table's `filters` state
     * @return array<int, array{0: Filter, 1: mixed}>
     */
    public function activeScoped(Table $table, array $filterValues): array
    {
        if ($table->getSubRowRelation() === null) {
            return [];
        }

        $active = [];

        foreach ($table->getFilters() as $filter) {
            if (! $filter->appliesToSubRows() || ! $filter->canView()) {
                continue;
            }

            $raw = data_get($filterValues, $filter->getName());
            if ($raw === null || $raw === '' || $raw === []) {
                continue;
            }

            $value = $filter->extractValue($raw);
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $active[] = [$filter, $value];
        }

        return $active;
    }

    /**
     * Constrain a child query by the active scoped main filters — the same
     * constraint TableQueryService used to whereHas the parents.
     *
     * Accepts a Builder or a Relation, because an eager-load closure receives
     * the latter; the filters always run against the Eloquent Builder.
     *
     * @template TQuery of Builder<Model>|EloquentRelation<Model, Model, mixed>
     *
     * @param  TQuery  $query
     * @param  array<string, mixed>  $filterValues
     * @return TQuery
     */
    public function applyScoped(Builder|EloquentRelation $query, Table $table, array $filterValues): Builder|EloquentRelation
    {
        $builder = $query instanceof EloquentRelation ? $query->getQuery() : $query;

        foreach ($this->activeScoped($table, $filterValues) as [$filter, $value]) {
            $filter->apply($builder, $value);
        }

        return $query;
    }

    /**
     * Constrain a child query by the interactive sub-row filter bar
     * (subRowsFilterable()).
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $subRowFilters  the `rows.subRowFilters` state
     * @return Builder<Model>
     */
    public function applyInteractive(Builder $query, Table $table, array $subRowFilters): Builder
    {
        if (! $table->isSubRowsFilterable() || empty($subRowFilters)) {
            return $query;
        }

        foreach ($table->getSubRowColumns() as $column) {
            $value = $subRowFilters[$column->getName()] ?? null;

            if ($value !== null && $value !== '' && $column->isFilterable()) {
                $query = $column->applyFilter($query, $value);
            }
        }

        return $query;
    }

    /**
     * Whether any interactive sub-row filter is active.
     *
     * Load-bearing rather than informational: it is what disables the
     * eager-load and in-memory fast paths, which would otherwise hand back
     * unfiltered children.
     *
     * @param  array<string, mixed>  $subRowFilters
     */
    public function hasActiveInteractive(Table $table, array $subRowFilters): bool
    {
        if (! $table->isSubRowsFilterable()) {
            return false;
        }

        foreach ($subRowFilters as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }
}
