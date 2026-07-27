<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use Carbon\Carbon;
use DateTimeInterface;
use NyonCode\WireCore\Exceptions\InvalidDateBoundaryException;
use Throwable;

/**
 * Canonical owner of "an owner-supplied date bound, in the exact shape the
 * widget compares against".
 *
 * Every surface with a min/max date — the date picker, a date filter, a date
 * column's header filter — lets the owner write whatever is natural (`now()`,
 * `'today'`, `'10.07.2026'`, `'+1 week'`), but both consumers of that bound
 * compare it as a plain string:
 *
 *  - a native `<input type="datetime-local">` ignores a `min` that is not
 *    `Y-m-d\TH:i` outright, so the bound just disappears;
 *  - the custom picker compares `'2026-07-15' < bound`, where a foreign shape
 *    disables every day (`'2026-07-15' < 'today'`) or none.
 *
 * Both failures are silent, which is why an unreadable bound throws here
 * instead of being passed along.
 */
final class DateBoundary
{
    /**
     * Lower bound, formatted for the widget.
     */
    public static function min(mixed $value, string $format): ?string
    {
        return self::parse($value)?->format($format);
    }

    /**
     * Upper bound, formatted for the widget.
     *
     * A day-granular bound on a datetime widget means "up to the end of that
     * day", not "up to its first second": `maxDate('2026-07-20')` has to leave
     * 20 July selectable at any time, so a bound that lands on midnight is
     * carried to the end of its day. Only a widget that shows both a date and a
     * time is affected — on a date-only widget the time is dropped by the
     * format anyway, and on a time-only one midnight is a deliberate choice.
     */
    public static function max(mixed $value, string $format): ?string
    {
        $date = self::parse($value);

        if ($date === null) {
            return null;
        }

        if (self::carriesDateAndTime($format) && $date->format('H:i:s') === '00:00:00') {
            $date = $date->endOfDay();
        }

        return $date->format($format);
    }

    /**
     * The date half of a normalized bound, or null when it carries none
     * (a time-only widget's bound).
     */
    public static function datePart(?string $bound): ?string
    {
        if ($bound === null || preg_match('/^\d{4}-\d{2}/', $bound) !== 1) {
            return null;
        }

        return preg_split('/[T ]/', $bound)[0];
    }

    /**
     * The time half of a normalized bound as `HH:MM:SS`, or null when it
     * carries none (a date-only widget's bound). Always padded to seconds so
     * that two halves stay comparable as strings.
     */
    public static function timePart(?string $bound): ?string
    {
        if ($bound === null) {
            return null;
        }

        $parts = preg_split('/[T ]/', $bound);
        $time = count($parts) > 1 ? $parts[1] : $parts[0];

        if (preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $time, $m) !== 1) {
            return null;
        }

        return $m[1].':'.$m[2].':'.($m[3] ?? '00');
    }

    private static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (! is_string($value)) {
            throw InvalidDateBoundaryException::unsupportedType(get_debug_type($value));
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw InvalidDateBoundaryException::unreadable($value);
        }
    }

    /**
     * Whether the widget's format shows a date and a time, so that a bound's
     * time half is meaningful on top of its date half.
     */
    private static function carriesDateAndTime(string $format): bool
    {
        return str_contains($format, 'Y') && str_contains($format, 'H');
    }
}
