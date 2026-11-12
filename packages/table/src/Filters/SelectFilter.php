<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Services\FilterFacets;

class SelectFilter extends Filter
{
    use HasNativeControl;
    use HasSheetOnMobile {
        HasSheetOnMobile::defaultSheetOnMobile as protected sheetConfigDefault;
    }

    /** @var array<string, string>|string|Closure */
    protected array|string|Closure $options = [];

    protected bool $searchable = false;

    /** Whether each option shows how many records it would leave. */
    protected bool $withCounts = false;

    /** Whether the options are the column's own distinct values, read from the data. */
    protected bool $optionsFromColumn = false;

    protected int $facetLimit = 100;

    /**
     * The counts {@see FilterFacets} took for this render, value => records.
     *
     * @var array<string, int>|null
     */
    protected ?array $facets = null;

    /**
     * Set the filter options as a value => label map, a closure, or an enum class-string.
     *
     * @param  array<string, string>|string|Closure  $options
     */
    public function options(array|string|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        $options = $this->normalizeOptions($this->options);

        // Read from the data: every distinct value the column holds (as far as
        // the other filters let it), most common first, labelled by itself —
        // or by an enum's label when the value is one.
        if ($options === [] && $this->optionsFromColumn && $this->facets !== null) {
            foreach (array_keys($this->facets) as $value) {
                $options[$value] = (string) EnumResolver::label($value);
            }
        }

        return $options;
    }

    /**
     * The options as a control draws them: with the record count beside each
     * label when {@see withCounts()} is on. The indicator chip and everything
     * else keep {@see getOptions()} — "Role: Admin", not "Role: Admin (3)".
     *
     * @return array<string, string>
     */
    public function getDisplayOptions(): array
    {
        $options = $this->getOptions();

        if (! $this->withCounts || $this->facets === null) {
            return $options;
        }

        foreach ($options as $value => $label) {
            $options[$value] = $label.' ('.($this->facets[(string) $value] ?? 0).')';
        }

        return $options;
    }

    /** Show beside each option how many records picking it would leave, counted under the other filters. */
    public function withCounts(bool $condition = true): static
    {
        $this->withCounts = $condition;

        return $this;
    }

    /**
     * Take the options from the column's own distinct values instead of a list
     * — a "set filter". Capped at `$limit` values, most common first.
     */
    public function optionsFromColumn(bool $condition = true, int $limit = 100): static
    {
        $this->optionsFromColumn = $condition;
        $this->facetLimit = max(1, $limit);

        return $this;
    }

    /** Whether this render has to count the options ({@see FilterFacets}). */
    public function wantsFacets(): bool
    {
        return $this->withCounts || $this->optionsFromColumn;
    }

    public function hasCounts(): bool
    {
        return $this->withCounts;
    }

    public function getFacetLimit(): int
    {
        return $this->facetLimit;
    }

    /**
     * Hand the filter this render's counts. Set by the host before it draws.
     *
     * @param  array<string, int>|null  $counts
     */
    public function facets(?array $counts): static
    {
        $this->facets = $counts;

        return $this;
    }

    /** @return array<string, int>|null */
    public function getFacets(): ?array
    {
        return $this->facets;
    }

    /** Render a searchable combobox instead of a native select. */
    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        // A searchable dropdown is the custom combobox, not the browser-native
        // <select>, so ->searchable() lifts an earlier ->native(); an explicit
        // later ->native() can still force the native element. Reset to the
        // default rather than pinned off, so ->nativeOnMobile() and its global
        // config still reach a searchable filter the way they reach a Select.
        if ($searchable && $this->native === true) {
            $this->native = null;
        }

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Searchable filters default to the classic floating dropdown on mobile so
     * the search input stays usable; non-searchable ones keep the global sheet
     * default. An explicit ->sheetOnMobile() still wins.
     */
    protected function defaultSheetOnMobile(): bool
    {
        return $this->isSearchable() ? false : $this->sheetConfigDefault();
    }

    /**
     * A select matches by membership: any array value is a whereIn (matching
     * any of the picked options), whether or not the filter is `multiple` and
     * through the relation when one is present. Scalars fall back to the base
     * equality / whereHas behaviour.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $this->normalizeValue($value), $value);
        }

        if ($this->hasOperatorPicker()) {
            $operator = $this->getActiveOperator();

            return $this->applyOperator($query, $operator, $operator->requiresValue() ? (array) $value : null);
        }

        if (! is_array($value)) {
            return parent::apply($query, $value);
        }

        $relation = $this->getRelation();

        if ($relation !== null) {
            $attribute = $this->relatedAttribute();

            /** @var Builder<Model> */
            return $query->whereHas(
                $relation,
                fn (Builder $q) => $q->whereIn($attribute, $value),
            );
        }

        /** @var Builder<Model> */
        return $query->whereIn($this->getColumn(), $value);
    }

    /** A condition about a select picks from its options. */
    public function builderInput(): string
    {
        return 'select';
    }

    /** "Is any of", "is none of" and emptiness: what a list of options can be asked. */
    public function supportedOperators(): array
    {
        return FilterOperator::forSelect();
    }

    /** A select matches by membership. */
    protected function baseOperator(): FilterOperator
    {
        return FilterOperator::In;
    }

    /**
     * With a picker, "is any of" is one IN clause the planner can join and
     * qualify; "is none of" keeps NULL rows and emptiness is not a value, so
     * those go through {@see apply()}. Without one, the base mapping.
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        if (! $this->hasOperatorPicker()) {
            return parent::toPlannerDefinitions($value);
        }

        $operator = $this->getActiveOperator();

        if ($this->queryCallback !== null || $operator !== FilterOperator::In || $value === null || $value === '' || $value === []) {
            return [];
        }

        return [FilterDefinition::make(
            column: $this->getColumn(),
            operator: 'in',
            value: array_values((array) $value),
        )];
    }

    public function inlineView(): string
    {
        return $this->multiple
            ? 'tables.columns.partials.filter-multi-select'
            : 'tables.columns.partials.filter-select';
    }

    public function isSelectLike(): bool
    {
        return true;
    }

    public function getFormFields(): array
    {
        return [
            Select::make('value')
                ->options($this->getOptions())
                ->placeholder($this->getPlaceholder())
                ->searchable($this->isSearchable()),
        ];
    }

    protected function filterView(): string
    {
        return 'tables.filters.select';
    }

    /**
     * Show option labels instead of raw option values in the indicator chip.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        $options = $this->getOptions();
        $values = is_array($value) ? $value : [$value];
        $labels = [];

        foreach ($values as $single) {
            if ($single === null || $single === '') {
                continue;
            }

            $labels[] = (string) ($options[$single] ?? $single);
        }

        return $labels === [] ? null : implode(', ', $labels);
    }

    /**
     * @param  array<array-key, mixed>|string|Closure  $options
     * @return array<array-key, mixed>
     */
    protected function normalizeOptions(array|string|Closure $options): array
    {
        return EnumResolver::normalizeOptions(value($options));
    }
}
