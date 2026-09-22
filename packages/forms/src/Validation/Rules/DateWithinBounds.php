<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Validation\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Holds a picked date, time or datetime to the bounds its field declares.
 *
 * `minDate()`, `maxDate()` and `disabledDates()` are drawn by the picker, and a
 * drawing is a courtesy, not a guarantee: a value can be typed into the custom
 * picker's box, and a phone's own date wheel does not honour `min`/`max` at all
 * (iOS offers every day). So the field repeats its own bounds as a rule, the
 * way `DateRangePicker` already adds `after_or_equal` for its second half.
 *
 * Everything arrives in the widget's state format — the bounds come from
 * `DateTimePicker::getMinDate()` / `getMaxDate()` in that same shape — and is
 * compared as instants, so `2026-07-10T08:30` and `2026-07-10T08:30:00` agree.
 */
final class DateWithinBounds implements ValidationRule
{
    /**
     * @param  list<string>  $disabledDates  `Y-m-d` days that cannot be picked
     * @param  string|null  $displayFormat  how a bound is named in the message
     */
    public function __construct(
        private readonly ?string $min = null,
        private readonly ?string $max = null,
        private readonly array $disabledDates = [],
        private readonly ?string $displayFormat = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $date = is_string($value) ? $this->parse($value) : null;

        if ($date === null) {
            $fail('wire-forms::fields.date.invalid')->translate();

            return;
        }

        if ($this->min !== null && ($min = $this->parse($this->min)) !== null && $date->lt($min)) {
            $fail('wire-forms::fields.date.min')->translate(['min' => $this->name($this->min, $min)]);

            return;
        }

        if ($this->max !== null && ($max = $this->parse($this->max)) !== null && $date->gt($max)) {
            $fail('wire-forms::fields.date.max')->translate(['max' => $this->name($this->max, $max)]);

            return;
        }

        if (in_array($date->format('Y-m-d'), $this->disabledDates, true)) {
            $fail('wire-forms::fields.date.disabled')->translate();
        }
    }

    private function parse(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            // A probe: "not a date" is the answer the rule reports, not a crash.
            return null;
        }
    }

    /** A bound as the user reads it: the field's display format, else its own text. */
    private function name(string $raw, Carbon $bound): string
    {
        return $this->displayFormat !== null ? $bound->format($this->displayFormat) : str_replace('T', ' ', $raw);
    }
}
