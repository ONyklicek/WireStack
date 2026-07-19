<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireForms\Components\DateTimePicker;

class DateFilter extends Filter
{
    protected bool $range = false;

    protected bool $monthMode = false;

    protected ?string $minDate = null;

    protected ?string $maxDate = null;

    protected ?string $fromLabel = null;

    protected ?string $toLabel = null;

    /** Filter by a from/to date range instead of a single date. */
    public function range(bool $range = true): static
    {
        $this->range = $range;

        return $this;
    }

    public function isRange(): bool
    {
        return $this->range;
    }

    /**
     * Filter by month + year instead of a full date. The value is a single
     * "YYYY-MM" string (native month input) applied as whereYear + whereMonth.
     *
     * Example: DateFilter::make('billed_at')->month()->subRows()
     */
    public function month(bool $month = true): static
    {
        $this->monthMode = $month;

        return $this;
    }

    public function isMonth(): bool
    {
        return $this->monthMode;
    }

    /**
     * whereYear/whereMonth cannot be expressed as a planner column definition.
     */
    public function bypassesPlanner(): bool
    {
        return $this->monthMode;
    }

    /** Set the earliest selectable date. */
    public function minDate(?string $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function getMinDate(): ?string
    {
        return $this->minDate;
    }

    /** Set the latest selectable date. */
    public function maxDate(?string $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    public function getMaxDate(): ?string
    {
        return $this->maxDate;
    }

    /** Set the label for the range's "from" input. */
    public function fromLabel(?string $label): static
    {
        $this->fromLabel = $label;

        return $this;
    }

    public function getFromLabel(): string
    {
        return $this->fromLabel ?? Trans::get('wire-table::messages.from');
    }

    /** Set the label for the range's "to" input. */
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

        if ($this->monthMode) {
            return $this->applyMonth($query, $column, $value);
        }

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

    /**
     * Apply a "YYYY-MM" month value as whereYear + whereMonth.
     * Malformed values are ignored rather than producing a broken constraint.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyMonth(Builder $query, string $column, mixed $value): Builder
    {
        if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{1,2})$/', $value, $matches)) {
            return $query;
        }

        $query->whereYear($column, (int) $matches[1]);
        $query->whereMonth($column, (int) $matches[2]);

        return $query;
    }

    /**
     * Date filtering always wants date-truncated comparison (whereDate /
     * whereYear+whereMonth), which the planner's plain column comparison cannot
     * express, so it is never planned — every mode routes through apply().
     * (bypassesPlanner() stays mode-specific for the sub-row/legacy path.)
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        return [];
    }

    public function inlineView(): string
    {
        return $this->range
            ? 'tables.columns.partials.filter-date-range'
            : 'tables.columns.partials.filter-date';
    }

    public function getFormFields(): array
    {
        if ($this->monthMode) {
            return [
                DateTimePicker::make('value')
                    ->asMonth()
                    ->minDate($this->minDate)
                    ->maxDate($this->maxDate),
            ];
        }

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

    public function getQueryStringFields(): array
    {
        if ($this->range) {
            return ['from' => '_from', 'to' => '_to'];
        }

        return parent::getQueryStringFields();
    }

    /**
     * Render the active value as "May 2026" (month mode), "from – to"
     * (range mode), or the single date.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($this->range && is_array($value)) {
            $from = ! empty($value['from']) ? (string) $value['from'] : null;
            $to = ! empty($value['to']) ? (string) $value['to'] : null;

            return match (true) {
                $from !== null && $to !== null => "{$from} – {$to}",
                $from !== null => "{$this->getFromLabel()} {$from}",
                $to !== null => "{$this->getToLabel()} {$to}",
                default => null,
            };
        }

        if ($this->monthMode && is_string($value) && preg_match('/^\d{4}-\d{1,2}$/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m', $value)->translatedFormat('F Y');
                // @codeCoverageIgnoreStart
                // Carbon overflows an out-of-range month (e.g. 2026-99) instead of
                // throwing, so with a regex-valid value this catch is unreachable —
                // kept as a defensive guard against locale/parser edge cases.
            } catch (\Throwable) {
                return $value;
            }
            // @codeCoverageIgnoreEnd
        }

        return parent::getIndicatorValueLabel($value);
    }
}
