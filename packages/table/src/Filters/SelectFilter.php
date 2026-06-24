<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;
use NyonCode\WireCore\Foundation\Support\EnumResolver;
use NyonCode\WireForms\Components\Select;

class SelectFilter extends Filter
{
    /** @var array<string, string>|Closure */
    protected array|Closure $options = [];

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

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
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

    protected function normalizeOptions(array|string|Closure $options): array
    {
        return EnumResolver::normalizeOptions(value($options));
    }
}
