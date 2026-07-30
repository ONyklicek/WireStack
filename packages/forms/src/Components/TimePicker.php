<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use NyonCode\WireForms\Exceptions\FormConfigurationException;

/**
 * Time-only picker, picked from a list of slots.
 *
 * Everything about the *value* is DateTimePicker's `time` mode — the `H:i` /
 * `H:i:s` state format, the bounds, the native `<input type="time">` fallback,
 * `displayFormat()`, the mobile sheet — so anything documented for `->asTime()`
 * holds here verbatim. What differs is how the time is chosen: a scrollable list
 * of times at a fixed interval (the Flux UI pattern) rather than the hour/minute
 * steppers `->asTime()` renders.
 *
 * That makes it deliberately more than a facade, and it is the one place this
 * package carries two ways to pick a time. `DateTimePicker::asTime()` keeps its
 * steppers untouched. See ADR 0008 § Amendment for why that split was accepted.
 *
 * The interval is the inherited `minutesStep()` — the same concept under the name
 * it already had, rather than a parallel `interval()` setter — defaulted to 30
 * here because a list at the picker-wide default of 1 would be 1440 rows long.
 *
 * @see DateTimePicker for the full API.
 * @see ADR 0008
 */
class TimePicker extends DateTimePicker
{
    /**
     * A slot list needs a usable stride, and DateTimePicker's default of "no step"
     * (falling back to one minute in the view) is one only a stepper can afford.
     * Flux's 30 minutes is the same choice for the same reason.
     */
    public const DEFAULT_INTERVAL_MINUTES = 30;

    protected string $mode = 'time';

    /**
     * Locked to `time` — the mode is this class's whole identity.
     *
     * `mode('date')`, and with it the inherited `asDate()` / `asMonth()` /
     * `asDateTime()` aliases that route through here, would leave a field whose
     * class name and behavior disagree. That is an authoring mistake with no
     * sensible reading, so it throws at declaration time rather than rendering a
     * calendar out of something called a TimePicker. Reach for
     * {@see DateTimePicker} when the mode has to vary.
     */
    public function mode(string $mode): static
    {
        if ($mode !== 'time') {
            throw FormConfigurationException::fixedPickerMode(static::class, 'time', $mode);
        }

        return parent::mode($mode);
    }

    /**
     * The gap between two slots, in minutes.
     *
     * Never null and never below 1: the view builds the list by walking the day in
     * these strides, so a zero would not render an empty list — it would not
     * terminate. `hoursStep()` and `secondsStep()` are inherited but unread here;
     * a slot list has one stride, not three.
     */
    public function getMinutesStep(): int
    {
        return max(1, parent::getMinutesStep() ?? self::DEFAULT_INTERVAL_MINUTES);
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.time-picker';
    }
}
