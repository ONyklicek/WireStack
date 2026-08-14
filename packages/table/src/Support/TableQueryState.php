<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Table;

/**
 * What the user has narrowed, sorted and paged the table to.
 *
 * One half of {@see TableRenderPlan}. It reads the state container — never the
 * legacy magic properties (`$component->tableFilters`, …), which rebuild the
 * deprecation map on every access and were easiest to reach for in exactly the
 * view block this replaces.
 *
 * The reading that earns the class is {@see $activeFilters}: "is a filter set"
 * has a sharp edge that plain `array_filter()` gets wrong, and it drives whether
 * the table offers to clear filters and which empty state it shows.
 */
final class TableQueryState
{
    /**
     * @param  mixed  $search  Untyped on purpose — the state container is
     *                         schema-driven but not type-checked, and this is a
     *                         pure lift of what the view already read.
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $columnFilters
     * @param  array<string, mixed>  $activeFilters  The subset a query acts on.
     * @param  array<string, mixed>  $activeColumnFilters
     * @param  mixed  $sortColumn  Untyped for the same reason as `$search`.
     * @param  bool  $hasActiveFilters  Whether the user narrowed the set at all;
     *                                  a search on its own counts.
     */
    private function __construct(
        public readonly mixed $search,
        public readonly array $filters,
        public readonly array $columnFilters,
        public readonly array $activeFilters,
        public readonly array $activeColumnFilters,
        public readonly mixed $sortColumn,
        public readonly string $sortDirection,
        public readonly int $perPage,
        public readonly bool $hasActiveFilters,
    ) {}

    public static function resolve(Table $table, mixed $component): self
    {
        $search = $component->tableState->get('search');
        $filters = $component->tableState->get('filters', []) ?? [];
        $columnFilters = $component->tableState->get('columnFilters', []) ?? [];

        $activeFilters = array_filter($filters, self::holdsValue(...));
        $activeColumnFilters = array_filter($columnFilters, self::holdsValue(...));

        return new self(
            search: $search,
            filters: $filters,
            columnFilters: $columnFilters,
            activeFilters: $activeFilters,
            activeColumnFilters: $activeColumnFilters,
            sortColumn: $component->tableState->get('sort.column'),
            sortDirection: (string) $component->tableState->get('sort.direction', 'asc'),
            perPage: (int) $component->tableState->get('pagination.perPage', $table->getPerPage()),
            hasActiveFilters: ! empty($search)
                || $activeFilters !== []
                || $activeColumnFilters !== [],
        );
    }

    /**
     * Whether a filter value is set to anything a query would act on.
     *
     * Recursive because a range filter's value is an array. A range typed and
     * then cleared leaves `['min' => '', 'max' => '']` — truthy to
     * `array_filter()`'s default callback, which would count the filter as active
     * and light up the "clear filters" affordance over nothing.
     */
    private static function holdsValue(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $inner) {
                if (self::holdsValue($inner)) {
                    return true;
                }
            }

            return false;
        }

        return $value !== null && $value !== '';
    }
}
