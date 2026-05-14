<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Trans;

class NumberRangeFilter extends Filter
{
    protected ?float $minValue = null;

    protected ?float $maxValue = null;

    protected ?float $step = null;

    protected ?string $minLabel = null;

    protected ?string $maxLabel = null;

    protected ?string $inputType = 'number';

    public function min(?float $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function getMin(): ?float
    {
        return $this->minValue;
    }

    public function max(?float $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function getMax(): ?float
    {
        return $this->maxValue;
    }

    public function step(?float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getStep(): ?float
    {
        return $this->step;
    }

    public function minLabel(?string $label): static
    {
        $this->minLabel = $label;

        return $this;
    }

    public function getMinLabel(): string
    {
        return $this->minLabel ?? Trans::get('wire-table::messages.from');
    }

    public function maxLabel(?string $label): static
    {
        $this->maxLabel = $label;

        return $this;
    }

    public function getMaxLabel(): string
    {
        return $this->maxLabel ?? Trans::get('wire-table::messages.to');
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
            return call_user_func($this->queryCallback, $query, $value);
        }

        $column = $this->getColumn();

        if (is_array($value)) {
            if (isset($value['min']) && $value['min'] !== '') {
                $query->where($column, '>=', (float) $value['min']);
            }
            if (isset($value['max']) && $value['max'] !== '') {
                $query->where($column, '<=', (float) $value['max']);
            }

            return $query;
        }

        return $query->where($column, $value);
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView('tables.filters.number-range'), [
            'filter' => $this,
            'value' => $value,
        ])->render();
    }
}
