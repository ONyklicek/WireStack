<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Concerns\HasAuthorization;
use NyonCode\WireForms\Components\TextInput;

/** @phpstan-consistent-constructor */
class Filter implements Htmlable
{
    use HasAuthorization;

    public string $name;

    public ?string $label = null;

    public ?string $column = null;

    public ?Closure $queryCallback = null;

    public mixed $default = null;

    public bool $hidden = false;

    public ?Closure $hiddenCallback = null;

    public ?string $placeholder = null;

    public bool $multiple = false;

    /** @var string|Closure|null Custom indicator chip label (string or fn ($value, Filter)) */
    protected string|Closure|null $indicator = null;

    /** @var string|null Relationship name for related model attributes */
    protected ?string $relation = null;

    /** Whether this filter targets the table's sub-row relation instead of parent columns */
    protected bool $appliesToSubRows = false;

    public function __construct(string $name)
    {
        $this->name = $name;
        $this->parseRelation($name);
    }

    private function parseRelation(string $name): void
    {
        if (Str::contains($name, '.')) {
            $parts = explode('.', $name);
            $this->relation = implode('.', array_slice($parts, 0, -1));
        }
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function column(?string $column): static
    {
        $this->column = $column;

        return $this;
    }

    public function query(Closure $callback): static
    {
        $this->queryCallback = $callback;

        return $this;
    }

    /**
     * Get the relationship name.
     */
    public function getRelation(): ?string
    {
        return $this->relation;
    }

    public function getQueryCallback(): ?Closure
    {
        return $this->queryCallback;
    }

    public function default(mixed $default): static
    {
        $this->default = $default;

        return $this;
    }

    public function visible(bool|Closure $visible = true): static
    {
        return $this->hidden(! $visible);
    }

    public function hidden(bool|Closure $hidden = true): static
    {
        if ($hidden instanceof Closure) {
            $this->hiddenCallback = $hidden;
        } else {
            $this->hidden = $hidden;
        }

        return $this;
    }

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Customize the indicator chip label shown while this filter is active.
     *
     * Accepts a fixed string or a closure receiving the unwrapped filter
     * value and the filter instance. Returning null/'' from the closure
     * hides the chip.
     */
    public function indicator(string|Closure|null $indicator): static
    {
        $this->indicator = $indicator;

        return $this;
    }

    /**
     * Indicator chip label for the given raw filter state, or null when
     * the filter is inactive (no chip).
     */
    public function getIndicator(mixed $raw): ?string
    {
        $value = $this->extractValue($raw);
        $valueLabel = $this->getIndicatorValueLabel($value);

        if ($valueLabel === null) {
            return null;
        }

        if ($this->indicator instanceof Closure) {
            $label = ($this->indicator)($value, $this);

            return ($label === null || $label === '') ? null : (string) $label;
        }

        if (is_string($this->indicator)) {
            return $this->indicator;
        }

        return $this->getLabel().': '.$valueLabel;
    }

    /**
     * Human-readable form of the active filter value, or null when the
     * value means "inactive". Concrete filters override this to map raw
     * values to option labels, ranges, formatted dates, etc.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            $values = array_filter($value, fn ($v) => $v !== null && $v !== '');

            return $values === [] ? null : implode(', ', array_map('strval', $values));
        }

        return (string) $value;
    }

    /**
     * Map of state sub-paths to URL parameter suffixes for query-string
     * persistence (Table::queryString()). The base {value: x} shape maps
     * to a single unsuffixed parameter; multi-field filters override this
     * (e.g. NumberRangeFilter → ['min' => '_min', 'max' => '_max']).
     *
     * @return array<string, string>
     */
    public function getQueryStringFields(): array
    {
        return ['value' => ''];
    }

    /**
     * Scope this filter to the table's sub-row relation (Table::subRows()).
     *
     * The filter then constrains child records instead of parent columns:
     * parents are reduced to those having at least one matching child
     * (whereHas), displayed sub-rows are limited to matching children, and
     * rollup aggregates (->sums(), ->counts(), …) only count matching children
     * — so footer grand totals reflect the filtered sub-rows.
     *
     * A ->query() callback on a sub-row scoped filter receives the CHILD
     * query builder, not the parent one.
     *
     * Ignored (treated as a regular parent filter) when the table has no
     * sub-row relation configured.
     */
    public function subRows(bool $subRows = true): static
    {
        $this->appliesToSubRows = $subRows;

        return $this;
    }

    public function appliesToSubRows(): bool
    {
        return $this->appliesToSubRows;
    }

    /**
     * Whether this filter must always be applied via apply() instead of the
     * QueryPlanner. Concrete filters whose constraint cannot be expressed as a
     * simple column/operator/value definition (e.g. DateFilter month mode)
     * override this.
     */
    public function bypassesPlanner(): bool
    {
        return false;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->queryCallback) {
            return ($this->queryCallback)($query, $value);
        }

        // If filter has a relation, it should be handled by WithTable::applyFilters
        // This is kept for backwards compatibility when used directly
        if ($this->relation) {
            $relation = $this->relation;
            $attribute = $this->getRelationshipAttribute();

            return $query->whereHas($relation, function ($q) use ($attribute, $value) {
                if ($this->multiple && is_array($value)) {
                    $q->whereIn($attribute, $value);
                } else {
                    $q->where($attribute, $value);
                }
            });
        }

        $column = $this->getColumn();

        if ($this->multiple && is_array($value)) {
            /** @var Builder<Model> */
            return $query->whereIn($column, $value);
        }

        /** @var Builder<Model> */
        return $query->where($column, $value);
    }

    /**
     * Get the relationship attribute of the column.
     */
    public function getRelationshipAttribute(): ?string
    {
        if (! $this->relation) {
            return null;
        }

        $parts = explode('.', $this->name);

        return end($parts);
    }

    public function getColumn(): string
    {
        return $this->column ?? $this->name;
    }

    public function toHtml(): string
    {
        return $this->render();
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView('tables.filters.form-field'), [
            'filter' => $this,
            'value' => $value,
        ])->render();
    }

    /**
     * Extract the value to compare against from raw filter state.
     *
     * Single-field filters store state as {value: 'x'} and unwrap to 'x'.
     * Multi-field filters (NumberRange {min, max}, DateFilter range {from, to})
     * keep the full array. Plain scalar inputs are returned as-is so programmatic
     * callers (and tests) can pass raw values without wrapping.
     */
    public function extractValue(mixed $raw): mixed
    {
        if (is_array($raw) && array_key_exists('value', $raw)) {
            return $raw['value'];
        }

        return $raw;
    }

    /**
     * Inverse of extractValue(): wrap a default/programmatic value into the
     * state shape used by the form-field wire:model paths. Multi-field filters
     * override this since their state is already a keyed array.
     */
    public function wrapValue(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('value', $value)) {
            return $value;
        }

        return ['value' => $value];
    }

    /**
     * Resolve filter view name with package namespace support.
     */
    protected function resolveFilterView(string $defaultView): string
    {
        $namespacedView = "wire-table::{$defaultView}";

        if (view()->exists($namespacedView)) {
            return $namespacedView;
        }

        return $defaultView;
    }

    public function canView(): bool
    {
        if ($this->isHidden()) {
            return false;
        }

        return $this->isAuthorized();
    }

    public function isHidden(): bool
    {
        if ($this->hiddenCallback) {
            return ($this->hiddenCallback)();
        }

        return $this->hidden;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? Str::headline($this->name);
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder ?? Trans::get('wire-table::messages.select_placeholder');
    }

    /**
     * Return the form field component(s) used to render this filter.
     *
     * Wire:model binding paths are relative to tableState.filters.{name}.
     * The base implementation returns a single text input named "value",
     * matching the {value: 'x'} state shape used by extractValue().
     *
     * @return array<int, mixed>
     */
    public function getFormFields(): array
    {
        return [
            TextInput::make('value')
                ->placeholder($this->placeholder ?? ''),
        ];
    }
}
