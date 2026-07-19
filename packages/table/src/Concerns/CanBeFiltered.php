<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\ColumnFilterFactory;

/**
 * Carrying an inline header filter.
 *
 * A column filter is a *placement* of a canonical {@see Filter} in the header
 * row: the column owns where it renders and which attribute it targets, the
 * Filter owns how to apply / render / indicate / persist. Which filter class
 * backs each `filterAs*()` is {@see ColumnFilterFactory}'s to know — nineteen of
 * these methods, and the imports of all five concrete filter types, used to sit
 * in Column itself.
 */
trait CanBeFiltered
{
    /**
     * The canonical Filter backing this column's inline header filter.
     * Built by filterable() / filterAs*(); null means not filterable.
     */
    protected ?Filter $filter = null;

    /**
     * Make this column filterable in its header.
     *
     * The `$type` string ('select', 'multi_select', 'date', 'date_range',
     * 'number_range', 'boolean') is kept for backward compatibility and maps to
     * the matching canonical Filter. Prefer the fluent filterAs*() helpers, or
     * pass a ready Filter to {@see Filter()}.
     *
     * @param  array<string, string>|class-string  $options
     */
    public function filterable(bool $filterable = true, string $type = 'text', array|string $options = []): static
    {
        if (! $filterable) {
            $this->filter = null;
            $this->capabilities = $this->capabilities->remove(Capability::Filterable);

            return $this;
        }

        return $this->filter($this->filterFactory()->ofType($type, $this->name, $options));
    }

    /**
     * Attach a fully-configured canonical Filter as this column's header filter.
     * The column injects its state-path prefix + inline variant when it resolves
     * the filter (see {@see resolveFilter()}).
     */
    public function filter(Filter $filter): static
    {
        $this->filter = $filter;
        $this->capabilities = $this->capabilities->add(Capability::Filterable);

        return $this;
    }

    public function getFilter(): ?Filter
    {
        return $this->filter;
    }

    /**
     * The header filter with the column's inline variant + `columnFilters`
     * state-path prefix applied, or null when the column is not filterable.
     */
    public function resolveFilter(): ?Filter
    {
        return $this->filter
            ?->statePathPrefix('tableState.columnFilters')
            ->inline();
    }

    /**
     * Configure as a searchable select column filter (single choice).
     *
     * @param  array<string, string>|class-string  $options
     */
    public function filterAsSelect(array|string $options, ?string $placeholder = null): static
    {
        return $this->filter($this->filterFactory()->select($this->name, $options, $placeholder));
    }

    /**
     * Configure as a multi-select column filter — the user can pick several
     * values and the column matches any of them (`whereIn`).
     *
     * @param  array<string, string>|class-string  $options
     */
    public function filterAsMultiSelect(array|string $options, ?string $placeholder = null): static
    {
        return $this->filter($this->filterFactory()->select($this->name, $options, $placeholder, multiple: true));
    }

    /**
     * Toggle the in-panel search box on a select / multi-select column filter.
     */
    public function filterSearchable(bool $condition = true): static
    {
        $filter = $this->ensureFilter();

        if ($filter instanceof SelectFilter) {
            $filter->searchable($condition);
        }

        return $this;
    }

    /** Add a single-date column header filter (optional min/max bounds). */
    public function filterAsDate(?string $minDate = null, ?string $maxDate = null): static
    {
        return $this->filter($this->filterFactory()->date($this->name, $minDate, $maxDate));
    }

    /** Configure as a date range column filter (from/to). */
    public function filterAsDateRange(?string $minDate = null, ?string $maxDate = null): static
    {
        return $this->filter($this->filterFactory()->date($this->name, $minDate, $maxDate, range: true));
    }

    /** Configure as a number range column filter (min/max). */
    public function filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null): static
    {
        return $this->filter($this->filterFactory()->numberRange($this->name, $min, $max, $step));
    }

    /** Configure as a boolean (yes/no/all) column filter. */
    public function filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null): static
    {
        return $this->filter($this->filterFactory()->boolean($this->name, $trueLabel, $falseLabel));
    }

    /**
     * Set the filter SQL operator (text filters only).
     * Supported: 'like', 'equals', 'starts_with', 'ends_with', '>', '>=', '<', '<=', '!='
     */
    public function filterOperator(string $operator): static
    {
        $filter = $this->ensureFilter();

        if ($filter instanceof TextFilter) {
            $filter->operator($operator);
        }

        return $this;
    }

    /** Set debounce for the text column filter input (in ms). */
    public function filterDebounce(int $ms): static
    {
        $filter = $this->ensureFilter();

        if ($filter instanceof TextFilter) {
            $filter->debounce($ms);
        }

        return $this;
    }

    public function isFilterable(): bool
    {
        return $this->filter !== null;
    }

    /**
     * Whether the filter binds an array state (multi-select), so the host can
     * seed `columnFilters.<name>` to [] for correct checkbox-group binding.
     */
    public function filterExpectsArray(): bool
    {
        return $this->filter !== null && $this->filter->isMultiple();
    }

    /** Set the placeholder for this column's header-filter control. */
    public function filterPlaceholder(?string $placeholder): static
    {
        $this->ensureFilter()->placeholder($placeholder);

        return $this;
    }

    /** Set a custom query callback for the column filter; the Closure receives the Builder + value and must return the Builder. */
    public function filterUsing(Closure $callback): static
    {
        $this->ensureFilter()->query($callback);

        return $this;
    }

    /**
     * Apply this column's header filter to the query, delegating to the
     * canonical Filter. A bare, non-relation column is qualified against the
     * base table so it stays unambiguous under joins (relation-manager pivots);
     * the clone keeps the stored filter (used for planning/render) untouched.
     */
    public function applyFilter(mixed $query, mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        $filter = $this->resolveFilter();

        if ($filter === null || ! $filter->canView()) {
            return $query;
        }

        if (! $this->hasRelation() && ! str_contains($this->name, '.') && $query instanceof Builder) {
            $filter = (clone $filter)->column($query->qualifyColumn($this->name));
        }

        return $filter->apply($query, $value);
    }

    /**
     * Lazily create a default (text) filter so modifier setters
     * (filterOperator / filterSearchable / …) work before any filterAs*().
     */
    protected function ensureFilter(): Filter
    {
        if ($this->filter === null) {
            $this->filter($this->filterFactory()->text($this->name));
        }

        return $this->filter;
    }

    protected function filterFactory(): ColumnFilterFactory
    {
        return app(ColumnFilterFactory::class);
    }
}
