<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Components\Select;

class SelectFilter extends Filter
{
    use HasSheetOnMobile {
        HasSheetOnMobile::defaultSheetOnMobile as protected sheetConfigDefault;
    }

    /** @var array<string, string>|string|Closure */
    protected array|string|Closure $options = [];

    protected bool $native = true;

    protected bool $searchable = false;

    /**
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
        return $this->normalizeOptions($this->options);
    }

    public function native(bool $native = true): static
    {
        $this->native = $native;

        return $this;
    }

    public function isNative(): bool
    {
        return $this->native;
    }

    public function searchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        // A searchable dropdown is the custom combobox, not the browser-native
        // <select>. Opt out of native rendering so ->searchable() works on its
        // own; an explicit later ->native() can still force the native element.
        if ($searchable) {
            $this->native = false;
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
            return ($this->queryCallback)($query, $value);
        }

        if (! is_array($value)) {
            return parent::apply($query, $value);
        }

        if ($this->getRelation() !== null) {
            $attribute = $this->getRelationshipAttribute();

            /** @var Builder<Model> */
            return $query->whereHas(
                $this->getRelation(),
                fn (Builder $q) => $q->whereIn($attribute, $value),
            );
        }

        /** @var Builder<Model> */
        return $query->whereIn($this->getColumn(), $value);
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

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView('tables.filters.select'), [
            'filter' => $this,
            'value' => $value,
        ])->render();
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
