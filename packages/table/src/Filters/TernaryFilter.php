<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Components\Select;

class TernaryFilter extends Filter
{
    protected ?string $trueLabel = null;

    protected ?string $falseLabel = null;

    protected ?string $allLabel = null;

    protected bool $nullable = false;

    public function trueLabel(?string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(?string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    public function allLabel(?string $label): static
    {
        $this->allLabel = $label;

        return $this;
    }

    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        if ($this->queryCallback) {
            return ($this->queryCallback)($query, $value);
        }

        $column = $this->getColumn();

        if ($value === 'true' || $value === '1' || $value === true) {
            return $query->where($column, true);
        }

        if ($value === 'false' || $value === '0' || $value === false) {
            if ($this->nullable) {
                return $query->where(function ($q) use ($column) {
                    $q->where($column, false)->orWhereNull($column);
                });
            }

            return $query->where($column, false);
        }

        return $query;
    }

    public function getFormFields(): array
    {
        return [
            Select::make('value')
                ->options([
                    '1' => $this->getTrueLabel(),
                    '0' => $this->getFalseLabel(),
                ])
                ->placeholder($this->getAllLabel()),
        ];
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view($this->resolveFilterView('tables.filters.ternary'), [
            'filter' => $this,
            'value' => $value,
        ])->render();
    }

    /**
     * Show the true/false option label in the indicator chip.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($value === 'true' || $value === '1' || $value === true) {
            return $this->getTrueLabel();
        }

        if ($value === 'false' || $value === '0' || $value === false) {
            return $this->getFalseLabel();
        }

        return null;
    }

    public function getAllLabel(): string
    {
        return $this->allLabel ?? Trans::get('wire-table::messages.filter_all');
    }

    public function getTrueLabel(): string
    {
        return $this->trueLabel ?? Trans::get('wire-table::messages.filter_yes');
    }

    public function getFalseLabel(): string
    {
        return $this->falseLabel ?? Trans::get('wire-table::messages.filter_no');
    }
}
