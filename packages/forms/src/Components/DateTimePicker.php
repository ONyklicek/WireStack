<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

/**
 * Unified date/time picker field.
 *
 * Replaces the three original classes (DatePicker, TimePicker, DateTimePicker)
 * with a single class using ->mode('date'|'time'|'datetime').
 *
 * @see ADR 0008
 */
class DateTimePicker extends Field
{
    protected string $mode = 'datetime';

    protected ?string $format = null;

    protected ?string $displayFormat = null;

    protected string|Closure|null $minDate = null;

    protected string|Closure|null $maxDate = null;

    protected bool $native = false;

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

    public function mode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function asDate(): static
    {
        return $this->mode('date');
    }

    public function asTime(): static
    {
        return $this->mode('time');
    }

    public function asDateTime(): static
    {
        return $this->mode('datetime');
    }

    // ─── Format ────────────────────────────────────────────────────

    public function format(?string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function displayFormat(?string $format): static
    {
        $this->displayFormat = $format;

        return $this;
    }

    // ─── Constraints ───────────────────────────────────────────────

    public function minDate(string|Closure|null $date): static
    {
        $this->minDate = $date;

        return $this;
    }

    public function maxDate(string|Closure|null $date): static
    {
        $this->maxDate = $date;

        return $this;
    }

    public function native(bool $condition = true): static
    {
        $this->native = $condition;

        return $this;
    }

    public function firstDayOfWeek(?int $day): static
    {
        $this->firstDayOfWeek = $day;

        return $this;
    }

    /**
     * @param  array<int, string>|Closure  $dates
     */
    public function disabledDates(array|Closure $dates): static
    {
        $this->disabledDates = $dates;

        return $this;
    }

    public function closeOnDateSelection(bool $condition = true): static
    {
        $this->closeOnDateSelection = $condition;

        return $this;
    }

    // ─── Time settings ─────────────────────────────────────────────

    public function withSeconds(bool $condition = true): static
    {
        $this->withSeconds = $condition;

        return $this;
    }

    public function hoursStep(?int $step): static
    {
        $this->hoursStep = $step;

        return $this;
    }

    public function minutesStep(?int $step): static
    {
        $this->minutesStep = $step;

        return $this;
    }

    public function secondsStep(?int $step): static
    {
        $this->secondsStep = $step;

        return $this;
    }

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
                'time' => $this->withSeconds
                    ? config('wire-forms.time_format', 'H:i').':s'
                    : config('wire-forms.time_format', 'H:i'),
                default => config('wire-forms.datetime_format', 'Y-m-d H:i'),
            };
        } catch (\Throwable) {
            return match ($this->mode) {
                'date' => 'Y-m-d',
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

    public function isNative(): bool
    {
        return $this->native;
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
            'time' => 'time',
            default => 'datetime-local',
        };
    }

    public function getStateType(): string
    {
        return 'datetime';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.date-time-picker';
    }
}
