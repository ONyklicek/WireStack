<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;

/**
 * Unified date/time picker field.
 *
 * Replaces the three original classes (DatePicker, TimePicker, DateTimePicker)
 * with a single class using ->mode('date'|'time'|'datetime').
 *
 * @see ADR 0008
 */
class DateTimePicker extends Field implements DehydratesState, HydratesState
{
    use HasNativeControl {
        HasNativeControl::isNative as protected nativeChoice;
    }
    use HasSheetOnMobile;

    protected string $mode = 'datetime';

    protected ?string $format = null;

    protected ?string $displayFormat = null;

    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    protected ?int $firstDayOfWeek = null;

    /** @var array<int, string>|Closure */
    protected array|Closure $disabledDates = [];

    protected bool $closeOnDateSelection = false;

    protected bool $withSeconds = false;

    protected ?int $hoursStep = null;

    protected ?int $minutesStep = null;

    protected ?int $secondsStep = null;

    protected ?string $timezone = null;

    // ─── Mode ──────────────────────────────────────────────────────

    /** Set the picker mode directly (date, time, or datetime). */
    public function mode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    /** Pick a date only. */
    public function asDate(): static
    {
        return $this->mode('date');
    }

    /**
     * Month + year picking ("YYYY-MM"). Always renders as a native
     * <input type="month"> — the custom calendar has no month-only grid.
     */
    public function asMonth(): static
    {
        return $this->mode('month');
    }

    /** Pick a time only. */
    public function asTime(): static
    {
        return $this->mode('time');
    }

    /** Pick both date and time. */
    public function asDateTime(): static
    {
        return $this->mode('datetime');
    }

    // ─── Format ────────────────────────────────────────────────────

    /** Set the display and storage date format. */
    public function format(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    /**
     * How the picked value is shown to the user, in PHP date() tokens
     * (`d.m.Y`, `j. n. Y H:i`, …). The stored value is unaffected.
     *
     * Honoured by the custom picker only: a native input's display format is
     * the browser's business, driven by the user's locale.
     */
    public function displayFormat(?string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    // ─── Constraints ───────────────────────────────────────────────

    /** Set the earliest selectable date. */
    public function minDate(string|Closure|null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    /** Set the latest selectable date. */
    public function maxDate(string|Closure|null $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    /** Set the first day of the week in the calendar (0 = Sunday). */
    public function firstDayOfWeek(?int $day): static
    {
        $this->firstDayOfWeek = $day;

        return $this;
    }

    /**
     * Mark specific dates as non-selectable.
     *
     * @param  array<int, string>|Closure  $dates
     */
    public function disabledDates(array|Closure $dates): static
    {
        $this->disabledDates = $dates;

        return $this;
    }

    /** Close the picker as soon as a date is chosen. */
    public function closeOnDateSelection(bool $condition = true): static
    {
        $this->closeOnDateSelection = $condition;

        return $this;
    }

    // ─── Time settings ─────────────────────────────────────────────

    /** Include seconds in the time picker. */
    public function withSeconds(bool $condition = true): static
    {
        $this->withSeconds = $condition;

        return $this;
    }

    /** Set the hour increment step. */
    public function hoursStep(?int $step): static
    {
        $this->hoursStep = $step;

        return $this;
    }

    /** Set the minute increment step. */
    public function minutesStep(?int $step): static
    {
        $this->minutesStep = $step;

        return $this;
    }

    /** Set the second increment step. */
    public function secondsStep(?int $step): static
    {
        $this->secondsStep = $step;

        return $this;
    }

    /** Set the timezone used to interpret and display the value. */
    public function timezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    // ─── Getters ───────────────────────────────────────────────────

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getFormat(): string
    {
        if ($this->format) {
            return $this->format;
        }

        try {
            return match ($this->mode) {
                'date' => config('wire-forms.date_format', 'Y-m-d'),
                'month' => 'Y-m',
                'time' => $this->withSeconds
                    ? config('wire-forms.time_format', 'H:i').':s'
                    : config('wire-forms.time_format', 'H:i'),
                default => config('wire-forms.datetime_format', 'Y-m-d H:i'),
            };
        } catch (\Throwable) {
            return match ($this->mode) {
                'date' => 'Y-m-d',
                'month' => 'Y-m',
                'time' => $this->withSeconds ? 'H:i:s' : 'H:i',
                default => 'Y-m-d H:i',
            };
        }
    }

    public function getDisplayFormat(): ?string
    {
        return $this->displayFormat;
    }

    public function getMinDate(): ?string
    {
        return $this->evaluate($this->minDate);
    }

    public function getMaxDate(): ?string
    {
        return $this->evaluate($this->maxDate);
    }

    /**
     * Month mode has no custom picker — the browser's <input type="month"> is the
     * only UI there — so it forces the native control even over ->native(false).
     */
    public function isNative(): bool
    {
        return $this->nativeChoice() || $this->mode === 'month';
    }

    public function getFirstDayOfWeek(): int
    {
        if ($this->firstDayOfWeek !== null) {
            return $this->firstDayOfWeek;
        }

        try {
            return (int) config('wire-forms.first_day_of_week', 1);
        } catch (\Throwable) {
            return 1;
        }
    }

    /**
     * @return array<int, string>
     */
    public function getDisabledDates(): array
    {
        return $this->evaluate($this->disabledDates);
    }

    public function shouldCloseOnDateSelection(): bool
    {
        return $this->closeOnDateSelection;
    }

    public function hasSeconds(): bool
    {
        return $this->withSeconds;
    }

    public function getHoursStep(): ?int
    {
        return $this->hoursStep;
    }

    public function getMinutesStep(): ?int
    {
        return $this->minutesStep;
    }

    public function getSecondsStep(): ?int
    {
        return $this->secondsStep;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getNativeInputType(): string
    {
        return match ($this->mode) {
            'date' => 'date',
            'month' => 'month',
            'time' => 'time',
            default => 'datetime-local',
        };
    }

    /**
     * Stored value → widget state.
     *
     * State must stay in the widget's own parseable format (a native
     * <input type="date"> demands Y-m-d; the custom picker parses Y-m-d,
     * Y-m-d\TH:i and H:i), so format() cannot reach here — it describes how the
     * value is *stored*, and applies on the way out instead.
     *
     * timezone() does apply here: the value is stored in the app zone and shown
     * in the field's. Its counterpart in dehydrateState() converts back, and the
     * two must always ship together — converting only inbound would write the
     * shifted value straight back and silently move the time.
     */
    public function hydrateState(mixed $value, ?Model $record = null): mixed
    {
        $zone = $this->getTimezone();

        if ($zone === null || $value === null || $value === '' || ! $this->hasTimeComponent()) {
            return $value;
        }

        return $this->parse($value, config('app.timezone'))
            ?->setTimezone($zone)
            ->format($this->getStateFormat());
    }

    /**
     * Widget state → stored value: apply the field's timezone and format().
     */
    public function dehydrateState(mixed $state, ?Model $record = null): mixed
    {
        if ($state === null || $state === '') {
            return null;
        }

        // A date-only or time-only value carries no instant to convert; only a
        // datetime does. Shifting a bare date by a timezone would move the day.
        $zone = $this->hasTimeComponent() ? $this->getTimezone() : null;

        // Both knobs are opt-in. Without them the state IS what should be
        // stored, and reformatting anyway would be a silent BC break: getFormat()
        // falls back to config('wire-forms.date_format') — 'd.m.Y' in a default
        // install — so every field that never called format() would quietly start
        // writing '09.03.2026' into a date column that had held '2026-03-09'.
        if ($this->format === null && $zone === null) {
            return $state;
        }

        $date = $this->parse($state, $zone ?? config('app.timezone'));

        if ($date === null) {
            return $state;
        }

        if ($zone !== null) {
            $date = $date->setTimezone(config('app.timezone'));
        }

        // Only an explicit format() may change the stored shape; a timezone-only
        // field keeps writing the state's own format.
        return $date->format($this->format ?? $this->getStateFormat());
    }

    /**
     * Only a datetime has an instant a timezone can move; 'date', 'month' and
     * 'time' are wall-clock values that a conversion would corrupt.
     */
    private function hasTimeComponent(): bool
    {
        return $this->mode === 'datetime';
    }

    private function parse(mixed $value, string $timezone): ?Carbon
    {
        try {
            return Carbon::parse((string) $value, $timezone);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The format the widget's state is written in — what getStateType() casts to.
     */
    private function getStateFormat(): string
    {
        return substr($this->getStateType(), 5);
    }

    public function getStateType(): string
    {
        // Store a mode-appropriate date STRING (not a Carbon) so the value stays
        // serializable in Livewire state and parseable by the picker/native input.
        return 'date:'.match ($this->mode) {
            'date' => 'Y-m-d',
            'month' => 'Y-m',
            'time' => $this->hasSeconds() ? 'H:i:s' : 'H:i',
            // 'T' separator so the native datetime-local input accepts the value
            // (the custom picker's parser handles it too).
            default => $this->hasSeconds() ? 'Y-m-d\TH:i:s' : 'Y-m-d\TH:i',
        };
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.date-time-picker';
    }
}
