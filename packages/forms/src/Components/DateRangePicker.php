<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use NyonCode\WireCore\Foundation\Components\LayoutComponent;

/**
 * A period: two dates that belong together, picked as one control.
 *
 * The pair is **composed, not re-implemented**. Each end is an ordinary
 * {@see DateTimePicker} bound to its own column, so the calendar, the typing
 * parser, the native-input fallback, the mobile sheet and the timezone handling
 * are the ones that field already owns — a second calendar written for ranges
 * would start drifting from it the week it landed.
 *
 * What this class owns is only what the pair needs and neither end can know
 * alone:
 *
 *  - **the coupling.** The end picker cannot open before the start date and the
 *    start picker cannot pass the end date. The bound is reactive (`$get`), so
 *    it follows the other end as it is picked, and it is repeated as an
 *    `after_or_equal` rule, because a browser bound is a courtesy and a saved
 *    row is a fact.
 *  - **the presets.** "This month" is one click that writes both ends.
 *
 * Being a layout rather than a field is what makes it persist without a seam of
 * its own: the form flattens layout children into the field list, so the two
 * pickers are ordinary fields to filling, validation and saving, and a range
 * lands in the two date columns an application already has. Nothing here
 * invents a state shape.
 *
 * ```php
 * DateRangePicker::make('validity')
 *     ->from('valid_from')
 *     ->until('valid_to')
 *     ->presets()
 * ```
 */
class DateRangePicker extends LayoutComponent
{
    protected ?string $fromName = null;

    protected ?string $untilName = null;

    protected ?string $fromLabel = null;

    protected ?string $untilLabel = null;

    protected string|DateTimeInterface|Closure|null $minDate = null;

    protected string|DateTimeInterface|Closure|null $maxDate = null;

    protected ?string $displayFormat = null;

    protected bool $withTime = false;

    protected bool $required = false;

    protected bool $showPresets = false;

    /** @var array<string, array{0: string, 1: string}>|null */
    protected ?array $presets = null;

    protected ?Closure $configurePickers = null;

    /** @var array<int, DateTimePicker>|null */
    private ?array $pickers = null;

    /** The column the start of the period is stored in (default `{name}_from`). */
    public function from(string $name): static
    {
        $this->fromName = $name;

        return $this;
    }

    /** The column the end of the period is stored in (default `{name}_to`). */
    public function until(string $name): static
    {
        $this->untilName = $name;

        return $this;
    }

    /** Label the two ends; each falls back to "From" / "To". */
    public function fromLabel(?string $label): static
    {
        $this->fromLabel = $label;

        return $this;
    }

    public function untilLabel(?string $label): static
    {
        $this->untilLabel = $label;

        return $this;
    }

    /** The earliest date either end may hold (a value or a `$get`-aware Closure). */
    public function minDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    /** The latest date either end may hold (a value or a `$get`-aware Closure). */
    public function maxDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /** How each end is shown to the user, in PHP date() tokens; the stored value is unaffected. */
    public function displayFormat(?string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    /** Pick a time alongside each date, for a period that starts and ends at an hour. */
    public function withTime(bool $condition = true): static
    {
        $this->withTime = $condition;

        return $this;
    }

    /** Require both ends of the period. */
    public function required(bool $condition = true): static
    {
        $this->required = $condition;

        return $this;
    }

    /**
     * Offer one-click periods above the pickers.
     *
     * Called with nothing it offers the built-in set (today, this week, this
     * month, the last 30 days, this year). A map of `label => [from, to]` —
     * either date strings or anything Carbon parses — replaces it.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $presets
     */
    public function presets(array $presets = []): static
    {
        $this->showPresets = true;
        $this->presets = $presets === [] ? null : self::normalizePresets($presets);

        return $this;
    }

    /**
     * Reach both pickers to set anything this class does not re-expose —
     * `firstDayOfWeek()`, `disabledDates()`, `native()`, a field action.
     *
     * The callback runs once per end, with that end's picker.
     */
    public function configurePickers(?Closure $callback): static
    {
        $this->configurePickers = $callback;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    /** The state key the start of the period binds to. */
    public function getFromName(): string
    {
        return $this->fromName ?? $this->getName().'_from';
    }

    /** The state key the end of the period binds to. */
    public function getUntilName(): string
    {
        return $this->untilName ?? $this->getName().'_to';
    }

    public function getFromPicker(): DateTimePicker
    {
        return $this->buildPickers()[0];
    }

    public function getUntilPicker(): DateTimePicker
    {
        return $this->buildPickers()[1];
    }

    public function hasPresets(): bool
    {
        return $this->showPresets;
    }

    /**
     * The offered periods, each resolved to the pair of values the two pickers
     * hold — computed here so the browser never has to know what "this month"
     * means, and never disagrees with the server about today's date.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public function getPresets(): array
    {
        if (! $this->showPresets) {
            return [];
        }

        return $this->presets ?? self::defaultPresets();
    }

    /**
     * The two pickers, in order. Built once: the form prepares the children it
     * finds in the schema, and a rebuilt picker would lose the state path and
     * Livewire instance that preparation gave it.
     *
     * @return array<int, DateTimePicker>
     */
    public function getSchema(): array
    {
        return $this->buildPickers();
    }

    public function prepareChildren(string $parentPath = '', bool $live = false, mixed $livewire = null, bool $disabled = false): void
    {
        // The schema is built on demand, and preparation walks $this->schema —
        // so the pickers have to exist before it does.
        $this->buildPickers();

        parent::prepareChildren($parentPath, $live, $livewire, $disabled);
    }

    /**
     * @return array<int, DateTimePicker>
     */
    private function buildPickers(): array
    {
        if ($this->pickers !== null) {
            return $this->pickers;
        }

        $from = $this->picker($this->getFromName(), $this->fromLabel ?? (string) trans('wire-forms::fields.range.from'));
        $until = $this->picker($this->getUntilName(), $this->untilLabel ?? (string) trans('wire-forms::fields.range.to'));

        // The coupling, both ways: neither end may cross the other. Reactive
        // closures, so the bound follows what is picked at the other end — and
        // falls back to this range's own bound while that end is still empty.
        $from->maxDate(fn (callable $get): mixed => $get($this->getUntilName()) ?: $this->resolveBound($this->maxDate, $get));
        $until->minDate(fn (callable $get): mixed => $get($this->getFromName()) ?: $this->resolveBound($this->minDate, $get));

        // The browser bound is a courtesy — a pasted value, a stale tab or a
        // disabled picker can still send an end before the start, so the same
        // constraint is a rule. A Closure, because the path it names is only
        // absolute once the form has prepared the children.
        $until->rules(fn (): array => ['after_or_equal:'.$from->getStatePath()]);

        return $this->pickers = $this->schema = [$from, $until];
    }

    /**
     * A bound as the picker's own bound closure would see it. A Closure the
     * owner wrote is called with the picker's `$get`, so `minDate(fn ($get) =>
     * $get('contract_signed_at'))` reads live sibling state here too.
     */
    private function resolveBound(string|DateTimeInterface|Closure|null $bound, callable $get): mixed
    {
        return $bound instanceof Closure ? $bound($get) : $bound;
    }

    private function picker(string $name, string $label): DateTimePicker
    {
        $picker = DateTimePicker::make($name)->label($label);

        $this->withTime ? $picker->asDateTime() : $picker->asDate();

        $picker->minDate($this->minDate)
            ->maxDate($this->maxDate)
            ->displayFormat($this->displayFormat)
            ->required($this->required)
            // Both ends have to be readable server-side as the other one is
            // picked: the bound closures above run on the roundtrip.
            ->live();

        if ($this->configurePickers !== null) {
            ($this->configurePickers)($picker);
        }

        return $picker;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $presets
     * @return array<string, array{0: string, 1: string}>
     */
    private static function normalizePresets(array $presets): array
    {
        return array_map(
            static fn (array $range): array => [
                Carbon::parse($range[0])->format('Y-m-d'),
                Carbon::parse($range[1])->format('Y-m-d'),
            ],
            $presets,
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private static function defaultPresets(): array
    {
        $today = Carbon::today();

        return [
            (string) trans('wire-forms::fields.range.today') => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            (string) trans('wire-forms::fields.range.this_week') => [
                $today->copy()->startOfWeek()->format('Y-m-d'),
                $today->copy()->endOfWeek()->format('Y-m-d'),
            ],
            (string) trans('wire-forms::fields.range.this_month') => [
                $today->copy()->startOfMonth()->format('Y-m-d'),
                $today->copy()->endOfMonth()->format('Y-m-d'),
            ],
            (string) trans('wire-forms::fields.range.last_30_days') => [
                $today->copy()->subDays(29)->format('Y-m-d'),
                $today->format('Y-m-d'),
            ],
            (string) trans('wire-forms::fields.range.this_year') => [
                $today->copy()->startOfYear()->format('Y-m-d'),
                $today->copy()->endOfYear()->format('Y-m-d'),
            ],
        ];
    }

    protected function viewName(): string
    {
        return 'wire-forms::layouts.date-range-picker';
    }
}
