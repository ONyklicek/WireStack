<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Carbon\Carbon;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\CanBeTyped;
use NyonCode\WireCore\Foundation\Concerns\HasExtraInputAttributes;
use NyonCode\WireCore\Foundation\Concerns\HasNativeControl;
use NyonCode\WireCore\Foundation\Concerns\HasSheetOnMobile;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireCore\Foundation\Support\DateBoundary;
use NyonCode\WireForms\Contracts\ProvidesImplicitValidationRules;
use NyonCode\WireForms\Validation\Rules\DateWithinBounds;

/**
 * Unified date/time picker field.
 *
 * Replaces the three original classes (DatePicker, TimePicker, DateTimePicker)
 * with a single class using ->mode('date'|'time'|'datetime').
 *
 * @see ADR 0008
 */
class DateTimePicker extends Field implements DehydratesState, HydratesState, ProvidesImplicitValidationRules
{
    use CanBeTyped;
    use HasExtraInputAttributes;
    use HasNativeControl {
        HasNativeControl::isNative as protected nativeChoice;
    }
    use HasSheetOnMobile;

    protected string $mode = 'datetime';

    protected ?string $format = null;

    protected ?string $displayFormat = null;

    protected string|DateTimeInterface|Closure|null $minDate = null;

    protected string|DateTimeInterface|Closure|null $maxDate = null;

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

    /**
     * Set the earliest selectable date.
     *
     * Takes anything readable as a date — a Carbon/DateTimeInterface, or a
     * string such as '2026-07-10', '10.07.2026', 'today' or '+1 week'. On a
     * datetime picker a bound may carry a time ('2026-07-10 08:30'), which then
     * limits the clock on that day too.
     */
    public function minDate(string|DateTimeInterface|Closure|null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    /**
     * Set the latest selectable date.
     *
     * A day-granular bound covers the whole day: on a datetime picker
     * `maxDate('2026-07-20')` leaves 20 July selectable up to 23:59.
     */
    public function maxDate(string|DateTimeInterface|Closure|null $date): static
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
            // Standalone use, with no container to read config from — the same
            // case the modals guard. These literals are the shipped defaults,
            // so the fallback answers exactly what config() would have.
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

    /**
     * Whether the trigger really accepts typing right now.
     *
     * `typeable()` is the owner's choice, but `readOnly()` and `disabled()`
     * outrank it — both mean "this value is not yours to change", and a keyboard
     * is a way to change it. Resolved here because both picker views need the
     * same answer, and a predicate re-spelled in two Blade files is one that
     * ends up disagreeing with itself.
     *
     * Custom picker only. A native control's keyboard belongs to the browser;
     * the only way to take it away is `readonly`, which would disable the
     * browser's own picker along with it.
     */
    public function acceptsTypedInput(): bool
    {
        return $this->isTypeable() && ! $this->isReadOnly() && ! $this->isDisabled();
    }

    /**
     * The format a typed value is read back through.
     *
     * The trigger shows `displayFormat()` when there is one and the raw state
     * otherwise, so the parser has to invert whichever of the two the user is
     * looking at. Resolved here rather than rebuilt in JS: the mode already
     * decides the state's shape, and only one of the two places should know it.
     */
    public function getTypedFormat(): string
    {
        return $this->displayFormat ?? $this->getStateFormat();
    }

    /**
     * The lower bound in the widget's own format — the only shape a native
     * input honours and the custom picker can compare against.
     */
    public function getMinDate(): ?string
    {
        return DateBoundary::min($this->evaluate($this->minDate), $this->getStateFormat());
    }

    public function getMaxDate(): ?string
    {
        return DateBoundary::max($this->evaluate($this->maxDate), $this->getStateFormat());
    }

    /**
     * Month mode has no custom picker — the browser's <input type="month"> is the
     * only UI there — so it forces the native control even over ->native(false).
     */
    public function isNative(): bool
    {
        return $this->nativeChoice() || $this->mode === 'month';
    }

    /**
     * The picker's bounds, repeated on the server — see {@see DateWithinBounds}.
     * Nothing to hold, no rule: a plain date field keeps exactly the rules its
     * owner wrote.
     *
     * @return array<int, mixed>
     */
    public function implicitValidationRules(): array
    {
        $min = $this->getMinDate();
        $max = $this->getMaxDate();
        $disabled = $this->mode === 'time' ? [] : $this->getDisabledDates();

        if ($min === null && $max === null && $disabled === []) {
            return [];
        }

        $rule = new DateWithinBounds($min, $max, $disabled, $this->getDisplayFormat());

        return $this->isRequired() ? [$rule] : ['nullable', $rule];
    }

    /** A time or a datetime — a value with a clock half the browser has to pick. */
    private function hasClock(): bool
    {
        return $this->mode === 'time' || $this->mode === 'datetime';
    }

    /**
     * The clock step, in minutes, when the field has one — or null.
     *
     * A step decides the native control. To the browser `step` is a validation
     * rule, not a wheel: iOS offers every minute whatever it says, then the
     * field refuses the pick. So a stepped clock goes native as a `<select>` of
     * these slots instead of `<input type="time">` (with a native date input
     * beside it on a datetime), and a phone can only give back a slot.
     */
    public function getSlotInterval(): ?int
    {
        if (! $this->hasClock()) {
            return null;
        }

        return match (true) {
            ($this->getMinutesStep() ?? 1) > 1 => $this->getMinutesStep(),
            ($this->getHoursStep() ?? 1) > 1 => $this->getHoursStep() * 60,
            default => null,
        };
    }

    /**
     * The touch control here is the wheel (`partials.wheel-picker`): a time, a
     * date or a datetime. A month is the browser's control everywhere.
     */
    protected function supportsTouchOnMobile(): bool
    {
        return in_array($this->mode, ['time', 'date', 'datetime'], true);
    }

    /** Whether the native control is a list of clock slots rather than a time input. */
    public function usesNativeSlots(): bool
    {
        return $this->getSlotInterval() !== null;
    }

    /**
     * The clock slots as a native `<select>`'s options: `value => label`, in the
     * state's clock shape, walking the day at {@see getSlotInterval()}.
     *
     * A time field leaves out what its bounds forbid, so the list offers only
     * what will save. A datetime's bounds belong to a day, not to the clock, so
     * its slots are the whole day and the server rule holds the boundary days.
     *
     * A current value between two slots (the interval changed, or it was typed
     * on a desktop) is kept in the list, in order, so the element shows it
     * instead of silently blanking.
     *
     * @return array<string, string>
     */
    public function getSlotOptions(mixed $state = null): array
    {
        $interval = max(1, $this->getSlotInterval() ?? 1);
        $bounded = $this->mode === 'time';
        $min = $bounded ? DateBoundary::timePart($this->getMinDate()) : null;
        $max = $bounded ? DateBoundary::timePart($this->getMaxDate()) : null;
        // The state is H:i, or H:i:s with seconds; slots key by that shape.
        $length = $this->hasSeconds() ? 8 : 5;
        $slots = [];

        for ($minute = 0; $minute < 24 * 60; $minute += $interval) {
            $time = sprintf('%02d:%02d:00', intdiv($minute, 60), $minute % 60);

            if (($min !== null && $time < $min) || ($max !== null && $time > $max)) {
                continue;
            }

            $slots[substr($time, 0, $length)] = $time;
        }

        $current = is_string($state) && $state !== '' ? DateBoundary::timePart($state) : null;

        if ($current !== null) {
            $slots[substr($current, 0, $length)] = $current;
            asort($slots);
        }

        return array_map(fn (string $time): string => $this->formatSlotLabel($time), $slots);
    }

    /** A slot as the custom list labels it: displayFormat() when set, else the bare time. */
    private function formatSlotLabel(string $time): string
    {
        // A datetime's display format carries a date a bare slot does not have.
        $display = $this->mode === 'time' ? $this->getDisplayFormat() : null;

        if ($display === null) {
            return substr($time, 0, $this->hasSeconds() ? 8 : 5);
        }

        return Carbon::createFromFormat('H:i:s', $time)->format($display);
    }

    public function getFirstDayOfWeek(): int
    {
        if ($this->firstDayOfWeek !== null) {
            return $this->firstDayOfWeek;
        }

        try {
            return (int) config('wire-forms.first_day_of_week', 1);
        } catch (\Throwable) {
            // As above: no container, and 1 (Monday) is the shipped default.
            return 1;
        }
    }

    /**
     * The calendar matches these against its own `Y-m-d` cells, so they go
     * through the same normalization as the bounds.
     *
     * @return array<int, string>
     */
    public function getDisabledDates(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $date): ?string => DateBoundary::min($date, 'Y-m-d'),
            $this->evaluate($this->disabledDates),
        )));
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
     * The native input's `step`, in seconds, or null for the browser's default.
     *
     * It is what makes a native time show seconds at all — the default step is
     * a minute — and the stride the browser's own validation holds a value to.
     * The finest configured unit wins: seconds, then minutes, then hours. A date
     * or a month has no clock, so no step.
     */
    public function getNativeStep(): ?int
    {
        if ($this->mode !== 'time' && $this->mode !== 'datetime') {
            return null;
        }

        return match (true) {
            $this->hasSeconds() => max(1, $this->getSecondsStep() ?? 1),
            $this->getMinutesStep() !== null => max(1, $this->getMinutesStep()) * 60,
            $this->getHoursStep() !== null => max(1, $this->getHoursStep()) * 3600,
            default => null,
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
            // A probe over whatever the model or the request handed over: an
            // empty string, a half-typed date, a column holding something else.
            // "Not a date" is the answer every caller here is asking for, and
            // each has its own empty state for it — a picker that throws while
            // the user is still typing is the worse failure.
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
