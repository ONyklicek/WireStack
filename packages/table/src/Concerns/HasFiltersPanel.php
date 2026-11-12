<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Enums\FiltersLayout;

/**
 * How the filter panel behaves and where it sits: deferred behind an Apply
 * button or live, in the toolbar dropdown or above the rows, and in how many
 * columns.
 */
trait HasFiltersPanel
{
    protected ?bool $filtersDeferred = null;

    protected ?FiltersLayout $filtersLayout = null;

    protected int $filtersFormColumns = 1;

    /**
     * Apply the panel filters only when the user presses Apply, instead of on
     * every change — one query for "pick three things", not three.
     */
    public function deferFilters(bool $deferred = true): static
    {
        $this->filtersDeferred = $deferred;

        return $this;
    }

    public function hasDeferredFilters(): bool
    {
        return $this->filtersDeferred ?? (bool) config('wire-table.defaults.defer_filters', false);
    }

    /** Where the filter panel is drawn: the toolbar dropdown, or above the rows (optionally collapsible). */
    public function filtersLayout(FiltersLayout|string $layout): static
    {
        $this->filtersLayout = $layout instanceof FiltersLayout ? $layout : FiltersLayout::from($layout);

        return $this;
    }

    public function getFiltersLayout(): FiltersLayout
    {
        return $this->filtersLayout
            ?? FiltersLayout::tryFrom((string) config('wire-table.defaults.filters_layout', 'dropdown'))
            ?? FiltersLayout::Dropdown;
    }

    /** Lay the filters out in this many columns (from the `sm` breakpoint up; one on a phone). */
    public function filtersFormColumns(int $columns): static
    {
        $this->filtersFormColumns = max(1, min(4, $columns));

        return $this;
    }

    public function getFiltersFormColumns(): int
    {
        return $this->filtersFormColumns;
    }

    /** The grid classes for {@see getFiltersFormColumns()} — literals, so Tailwind sees them. */
    public function getFiltersGridClass(): string
    {
        return match ($this->filtersFormColumns) {
            2 => 'grid gap-4 sm:grid-cols-2',
            3 => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3',
            4 => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-4',
            default => 'grid gap-4',
        };
    }

    /** The dropdown panel's width, wide enough for {@see getFiltersFormColumns()}. */
    public function getFiltersPanelWidthClass(): string
    {
        return match (true) {
            $this->filtersFormColumns >= 3 => 'w-72 sm:w-[44rem]',
            $this->filtersFormColumns === 2 => 'w-72 sm:w-[32rem]',
            default => 'w-72',
        };
    }
}
