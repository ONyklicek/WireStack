<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Filters;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\ViewErrorBag;
use NyonCode\WireCore\Core\Support\Trans;
use NyonCode\WireCore\Foundation\Support\DateBoundary;
use NyonCode\WireForms\Components\DateRangePicker;
use NyonCode\WireForms\Components\DateTimePicker;
use NyonCode\WireTable\Enums\DatePreset;
use NyonCode\WireTable\Enums\FilterOperator;

class DateFilter extends Filter
{
    protected bool $range = false;

    protected bool $monthMode = false;

    protected string|DateTimeInterface|null $minDate = null;

    protected string|DateTimeInterface|null $maxDate = null;

    protected ?string $fromLabel = null;

    protected ?string $toLabel = null;

    /**
     * The named ranges offered beside the dates; empty for none.
     *
     * @var list<DatePreset>
     */
    protected array $presets = [];

    /** The timezone "today" is read in; null is the application's. */
    protected ?string $timezone = null;

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
     * Every DateFilter mode needs a date-truncated comparison (whereDate, or
     * whereYear+whereMonth in month mode) that the planner's plain
     * column/operator/value definition cannot express — toPlannerDefinitions()
     * returns [] for all of them — so every mode must route through apply().
     *
     * This must stay true for the single-date mode too: its value is a scalar,
     * so without bypassing the planner it is neither routed to apply() (that
     * path only picks up callbacks, month mode, and the array range) nor turned
     * into a planner definition, and the filter silently does nothing.
     */
    public function bypassesPlanner(): bool
    {
        return true;
    }

    /**
     * Set the earliest selectable date — anything readable as one
     * (a DateTimeInterface, '2026-07-10', '10.07.2026', 'today', '-1 year').
     */
    public function minDate(string|DateTimeInterface|null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    /**
     * In the shape the inline `<input type="date|month">` needs it — any other
     * shape is dropped by the browser without a word.
     */
    public function getMinDate(): ?string
    {
        return DateBoundary::min($this->minDate, $this->boundaryFormat());
    }

    /** Set the latest selectable date. */
    public function maxDate(string|DateTimeInterface|null $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    public function getMaxDate(): ?string
    {
        return DateBoundary::max($this->maxDate, $this->boundaryFormat());
    }

    private function boundaryFormat(): string
    {
        return $this->monthMode ? 'Y-m' : 'Y-m-d';
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
    /**
     * Offer named ranges — today, last 7 days, this month… — beside the dates
     * (range mode only; turns range on). `true` offers them all, a list offers
     * those. What is stored is the name, so a saved view stays relative.
     *
     * @param  array<int, DatePreset|string>|bool  $presets
     */
    public function presets(array|bool $presets = true): static
    {
        $this->presets = match (true) {
            $presets === true => DatePreset::cases(),
            $presets === false => [],
            default => array_values(array_filter(array_map(
                static fn (DatePreset|string $preset): ?DatePreset => $preset instanceof DatePreset ? $preset : DatePreset::tryFrom($preset),
                $presets,
            ))),
        };

        if ($this->presets !== []) {
            $this->range = true;
        }

        return $this;
    }

    /** @return list<DatePreset> */
    public function getPresets(): array
    {
        return $this->presets;
    }

    public function hasPresets(): bool
    {
        return $this->presets !== [];
    }

    /** The timezone a preset's "today" is read in (defaults to the application's). */
    public function timezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    /** The preset the state names, if this filter offers it. */
    public function presetFromState(mixed $value): ?DatePreset
    {
        $preset = is_array($value) && is_string($value['preset'] ?? null) ? DatePreset::tryFrom($value['preset']) : null;

        return $preset !== null && in_array($preset, $this->presets, true) ? $preset : null;
    }

    /**
     * The dates a range state covers: a preset's, worked out now, or the
     * from/to typed in.
     *
     * @return array{from: mixed, to: mixed}
     */
    public function resolveRange(mixed $value): array
    {
        $preset = $this->presetFromState($value);

        if ($preset !== null) {
            return $preset->range(CarbonImmutable::now($this->timezone ?? config('app.timezone')));
        }

        return ['from' => $value['from'] ?? null, 'to' => $value['to'] ?? null];
    }

    /**
     * A single date compares by day: on, not on, before, after, and emptiness.
     * (A range has its own from/to and does not offer operators.)
     */
    public function supportedOperators(): array
    {
        if ($this->range || $this->monthMode) {
            return [];
        }

        return [FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::LessThan, FilterOperator::LessThanOrEqual, FilterOperator::GreaterThan, FilterOperator::GreaterThanOrEqual, FilterOperator::IsEmpty, FilterOperator::IsNotEmpty];
    }

    /** A condition about a date picks a day. */
    public function builderInput(): string
    {
        return 'date';
    }

    /**
     * The calendar a single date is picked with — the forms DateTimePicker, the
     * same control a form draws, bound straight to the filter's state path.
     */
    public function dateControl(string $statePath): DateTimePicker
    {
        $split = (int) strrpos($statePath, '.');

        $picker = DateTimePicker::make(substr($statePath, $split + 1))
            ->asDate()
            ->label($this->getLabel())
            ->hiddenLabel()
            ->placeholder($this->getPlaceholder())
            ->minDate($this->minDate)
            ->maxDate($this->maxDate)
            ->statePath(substr($statePath, 0, $split))
            ->live();

        $livewire = app('livewire')->current();
        if ($livewire !== null) {
            $picker->livewire($livewire);
        }

        return $picker;
    }

    /**
     * The calendar a range is picked with — the forms DateRangePicker: one box,
     * two months side by side, the presets beside them; on a phone the two
     * pickers with the presets as chips. Bound to `{from, to}` under the
     * filter's state path, and — with ->presets() — writing the preset's NAME
     * to `preset` too, so the range stays relative (DatePreset).
     */
    public function rangeControl(string $statePath): DateRangePicker
    {
        $range = DateRangePicker::make($this->name)
            ->from('from')
            ->until('to')
            ->label($this->getLabel())
            ->hiddenLabel()
            ->fromLabel($this->getFromLabel())
            ->untilLabel($this->getToLabel())
            ->minDate($this->minDate)
            ->maxDate($this->maxDate);

        if ($this->hasPresets()) {
            $now = CarbonImmutable::now($this->timezone ?? config('app.timezone'));
            $presets = [];

            foreach ($this->presets as $preset) {
                $dates = $preset->range($now);
                $presets[$preset->label()] = [$dates['from'], $dates['to'], $preset->value];
            }

            $range->presets($presets)->rememberPreset('preset');
        }

        $range->prepareChildren($statePath, live: true, livewire: app('livewire')->current());

        return $range;
    }

    /**
     * A forms picker as markup. The forms views read `$errors`, which the web
     * middleware shares on a page; a filter drawn outside one (a test, a
     * partial render) gets an empty bag rather than an undefined variable.
     */
    public function controlHtml(DateTimePicker|DateRangePicker $control): string
    {
        if (! view()->shared('errors')) {
            view()->share('errors', new ViewErrorBag);
        }

        return $control->toHtml();
    }

    /** Days are before and after, not less and greater. */
    public function operatorLabel(FilterOperator $operator): string
    {
        return match ($operator) {
            FilterOperator::Equals, FilterOperator::NotEquals, FilterOperator::LessThan, FilterOperator::LessThanOrEqual,
            FilterOperator::GreaterThan, FilterOperator::GreaterThanOrEqual => Trans::get('wire-table::messages.date_operator_'.$operator->value),
            default => $operator->label(),
        };
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '' || $value === []) {
            return $query;
        }

        if ($this->queryCallback) {
            return $this->applyQueryCallback($query, $this->normalizeValue($value), $value);
        }

        if ($this->hasOperatorPicker()) {
            return $this->applyDateOperator($query, $this->getActiveOperator(), $value);
        }

        $column = $this->getColumn();

        if ($this->monthMode) {
            return $this->applyMonth($query, $column, $value);
        }

        if ($this->range && is_array($value)) {
            $range = $this->resolveRange($value);

            if (! empty($range['from'])) {
                $query->whereDate($column, '>=', $range['from']);
            }
            if (! empty($range['to'])) {
                $query->whereDate($column, '<=', $range['to']);
            }

            return $query;
        }

        /** @var Builder<Model> */
        return $query->whereDate($column, $value);
    }

    /**
     * One day comparison. Compared by DATE, not by the stored value: on a
     * datetime column "on 3 March" has to match 3 March at 14:00.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyDateOperator(Builder $query, FilterOperator $operator, mixed $value): Builder
    {
        if ($operator->requiresValue() && (! is_string($value) || $value === '')) {
            return $query;
        }

        // Through applyComparison() so a dotted name ("customer.created_at")
        // goes through the relation, and "is not on" / "is empty" across it are
        // "no related record is on that day", like every other filter's.
        return $this->applyComparison(
            $query,
            $operator,
            static fn (FilterOperator $op, Builder $q, string $column) => self::constrainDate($op, $q, $column, $value),
        );
    }

    /**
     * One date comparison on one column, by day.
     *
     * @param  Builder<Model>  $query
     */
    private static function constrainDate(FilterOperator $operator, Builder $query, string $column, mixed $value): void
    {
        match ($operator) {
            FilterOperator::IsEmpty, FilterOperator::IsNotEmpty => $operator->constrain($query, $column, null),
            FilterOperator::NotEquals => $query->where(static fn (Builder $q) => $q->whereNull($column)->orWhereDate($column, '!=', $value)),
            FilterOperator::LessThan => $query->whereDate($column, '<', $value),
            FilterOperator::LessThanOrEqual => $query->whereDate($column, '<=', $value),
            FilterOperator::GreaterThan => $query->whereDate($column, '>', $value),
            FilterOperator::GreaterThanOrEqual => $query->whereDate($column, '>=', $value),
            default => $query->whereDate($column, $value),
        };
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
     * express, so it is never planned — every mode routes through apply()
     * (see bypassesPlanner()).
     */
    public function toPlannerDefinitions(mixed $value): array
    {
        return [];
    }

    /**
     * The panel draws a range and a single date with the forms pickers, as the
     * header does; a month keeps the plain month input of the shared template.
     */
    protected function filterView(): string
    {
        return match (true) {
            $this->monthMode => parent::filterView(),
            $this->range => 'tables.filters.date-range',
            default => 'tables.filters.date',
        };
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

    /** "Only this value" on a date cell is that day; a day cannot be excluded. */
    public function stateForCellValue(mixed $value, bool $exclude = false): mixed
    {
        if ($exclude || $value === null || $value === '' || $this->monthMode) {
            return null;
        }

        try {
            $day = Carbon::parse($value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }

        return $this->range
            ? array_merge((array) $this->emptyState(), ['from' => $day, 'to' => $day])
            : $day;
    }

    /** A range binds from/to (and preset), so all of them exist from the start. */
    public function emptyState(): mixed
    {
        if (! $this->range || $this->monthMode) {
            return parent::emptyState();
        }

        $state = ['from' => null, 'to' => null];

        if ($this->hasPresets()) {
            $state['preset'] = null;
        }

        return $state;
    }

    public function wrapValue(mixed $value): mixed
    {
        // A range's state is its keys, from the start: the picker binds into
        // `.from` / `.to`, and a binding into a key of null throws.
        if ($this->range) {
            return $value ?? $this->emptyState();
        }

        return parent::wrapValue($value);
    }

    public function getQueryStringFields(): array
    {
        if ($this->range) {
            return $this->hasPresets()
                ? ['from' => '_from', 'to' => '_to', 'preset' => '_preset']
                : ['from' => '_from', 'to' => '_to'];
        }

        return parent::getQueryStringFields();
    }

    /**
     * Render the active value as "May 2026" (month mode), "from – to"
     * (range mode), or the single date.
     */
    protected function getIndicatorValueLabel(mixed $value): ?string
    {
        if ($this->range && is_array($value) && ($preset = $this->presetFromState($value)) !== null) {
            return $preset->label();
        }

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
                return Carbon::createFromFormat('Y-m', $value)?->translatedFormat('F Y') ?? $value;
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
