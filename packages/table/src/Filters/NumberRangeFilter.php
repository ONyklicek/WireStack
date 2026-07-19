<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Query\FilterDefinition;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Components\TextInput;

class NumberRangeFilter extends Filter
{
    protected ?float $minValue = null;

    protected ?float $maxValue = null;

    protected ?float $step = null;

    protected ?string $minLabel = null;

    protected ?string $maxLabel = null;

    protected ?string $inputType = 'number';

    /** Set the lowest selectable value (input floor). */
    public function min(?float $value): static
    {
        $this->minValue = $value;

        return $this;
    }

    public function getMin(): ?float
    {
        return $this->minValue;
    }

    /** Set the highest selectable value (input ceiling). */
    public function max(?float $value): static
    {
        $this->maxValue = $value;

        return $this;
    }

    public function getMax(): ?float
    {
        return $this->maxValue;
    }

    /** Set the increment between selectable values. */
    public function step(?float $step): static
    {
        $this->step = $step;

        return $this;
    }

    public function getStep(): ?float
    {
        return $this->step;
    }

    /** Set the label for the range's minimum input. */
    public function minLabel(?string $label): static
    {
        $this->minLabel = $label;

        return $this;
    }

    public function getMinLabel(): string
    {
        return $this->minLabel ?? Trans::get('wire-table::messages.from');
    }

    /** Set the label for the range's maximum input. */
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
            return ($this->queryCallback)($query, $value);
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

    /**
     * Express the min/max bounds as a single BETWEEN clause so the range plans
     * through the QueryPlanner (the ApplyFilters pipe degrades a one-sided
     * BETWEEN to a `>=` / `<=` comparison). Returns [] when neither bound is
     * set so nothing is planned.
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        if ($this->queryCallback !== null || ! is_array($value)) {
            return [];
        }

        $min = (isset($value['min']) && $value['min'] !== '') ? (float) $value['min'] : null;
        $max = (isset($value['max']) && $value['max'] !== '') ? (float) $value['max'] : null;

        if ($min === null && $max === null) {
            return [];
        }

        return [FilterDefinition::make(
            column: $this->getColumn(),
            operator: 'BETWEEN',
            value: [$min, $max],
        )];
    }

    public function inlineView(): string
    {
        return 'tables.columns.partials.filter-number-range';
    }

    public function getFormFields(): array
    {
        return [
            TextInput::make('min')
                ->numeric()
                ->placeholder($this->getMinLabel()),
            TextInput::make('max')
                ->numeric()
                ->placeholder($this->getMaxLabel()),
        ];
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

    public function wrapValue(mixed $value): mixed
    {
        return $value;
    }

    public function getQueryStringFields(): array
    {
        return ['min' => '_min', 'max' => '_max'];
    }

    /**
     * Render the active bounds as "10 – 100", "≥ 10", or "≤ 100".
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if (! is_array($value)) {
            return parent::getIndicatorValueLabel($value);
        }

        $min = isset($value['min']) && $value['min'] !== '' ? (string) $value['min'] : null;
        $max = isset($value['max']) && $value['max'] !== '' ? (string) $value['max'] : null;

        return match (true) {
            $min !== null && $max !== null => "{$min} – {$max}",
            $min !== null => "≥ {$min}",
            $max !== null => "≤ {$max}",
            default => null,
        };
    }
}
