<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Components\DateTimePicker;

class DateFilter extends Filter
{
    protected bool $range = false;

    protected ?string $minDate = null;

    protected ?string $maxDate = null;

    protected ?string $fromLabel = null;

    protected ?string $toLabel = null;

    public function range(bool $range = true): static
    {
        $this->range = $range;

        return $this;
    }

    public function isRange(): bool
    {
        return $this->range;
    }

    public function minDate(?string $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function getMinDate(): ?string
    {
        return $this->minDate;
    }

    public function maxDate(?string $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    public function getMaxDate(): ?string
    {
        return $this->maxDate;
    }

    public function fromLabel(?string $label): static
    {
        $this->fromLabel = $label;

        return $this;
    }

    public function getFromLabel(): string
    {
        return $this->fromLabel ?? Trans::get('wire-table::messages.from');
    }

    public function toLabel(?string $label): static
    {
        $this->toLabel = $label;

        return $this;
    }

    public function getToLabel(): string
    {
        return $this->toLabel ?? Trans::get('wire-table::messages.to');
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

        if ($this->range && is_array($value)) {
            if (! empty($value['from'])) {
                $query->whereDate($column, '>=', $value['from']);
            }
            if (! empty($value['to'])) {
                $query->whereDate($column, '<=', $value['to']);
            }

            return $query;
        }

        /** @var Builder<Model> */
        return $query->whereDate($column, $value);
    }

    public function getFormFields(): array
    {
        if ($this->range) {
            return [
                DateTimePicker::make('from')
                    ->asDate()
                    ->placeholder($this->getFromLabel())
                    ->minDate($this->minDate)
                    ->maxDate($this->maxDate),
                DateTimePicker::make('to')
                    ->asDate()
                    ->placeholder($this->getToLabel())
                    ->minDate($this->minDate)
                    ->maxDate($this->maxDate),
            ];
        }

        return [
            DateTimePicker::make('value')
                ->asDate()
                ->minDate($this->minDate)
                ->maxDate($this->maxDate),
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
        if ($this->range) {
            return $value;
        }

        return parent::wrapValue($value);
    }
}
