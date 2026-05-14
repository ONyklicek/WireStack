<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Closure;

class SelectFilter extends Filter
{
    /** @var array<string, string> */
    protected array $options = [];

    protected bool $native = true;

    protected bool $searchable = false;

    /**
     * @param  array<string, string>|Closure  $options
     */
    public function options(array|Closure $options): static
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getOptions(): array
    {
        return $this->options;
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
}
