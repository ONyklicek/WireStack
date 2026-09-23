<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Support;

use NyonCode\WireCore\Widgets\DashboardFilter;

/**
 * What a dashboard's filters are set to right now, resolved.
 *
 * Built from the declared filters and the raw values the host holds (which came
 * from the address), so every reader asks one object that has already checked
 * them: the dashboard building its widgets, the filter bar drawing the current
 * selection, the grid deciding whether a widget that ignores filters needs its
 * mark. None of them re-checks a value against the options, and none of them
 * can disagree about what the selection is.
 */
final readonly class DashboardFilterState
{
    /**
     * @param  array<string, DashboardFilter>  $filters
     * @param  array<string, ?string>  $values  Resolved, keyed by filter name
     */
    private function __construct(
        private array $filters,
        private array $values,
    ) {}

    /**
     * @param  array<int, DashboardFilter>  $filters
     * @param  array<string, mixed>  $raw  What the host holds, unchecked
     */
    public static function resolve(array $filters, array $raw): self
    {
        $byName = [];
        $values = [];

        foreach ($filters as $filter) {
            $name = $filter->getName();
            $byName[$name] = $filter;
            $values[$name] = $filter->resolve($raw[$name] ?? null);
        }

        return new self($byName, $values);
    }

    public static function none(): self
    {
        return new self([], []);
    }

    /** The resolved value of one filter; null for "narrows nothing" or an unknown name. */
    public function value(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }

    /** @return array<string, ?string> */
    public function values(): array
    {
        return $this->values;
    }

    /** @return array<string, DashboardFilter> */
    public function filters(): array
    {
        return $this->filters;
    }

    public function has(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * Whether any filter is set to something other than its default.
     *
     * What the reset link and the "not filtered" mark on a widget both ask: a
     * dashboard at its defaults is the picture everybody gets, so neither has
     * anything to say.
     */
    public function isNarrowed(): bool
    {
        foreach ($this->filters as $name => $filter) {
            if ($this->values[$name] !== $filter->getDefault()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The values worth keeping in the address: only those that differ from the
     * default, so a dashboard nobody filtered has a clean URL.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        $query = [];

        foreach ($this->filters as $name => $filter) {
            $value = $this->values[$name];

            if ($value !== null && $value !== $filter->getDefault()) {
                $query[$name] = $value;
            }
        }

        return $query;
    }
}
