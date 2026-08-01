<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Search;

use Carbon\Carbon;
use Throwable;

/**
 * The span of time a typed date actually means.
 *
 * A user who types `2026-01-31` into a search box means the whole day, not its
 * first instant — on a `datetime` column the difference is every row after
 * midnight. The same holds one level up: `2026-01` is a month and `2026` is a
 * year, so the granularity of what was typed decides the width of the span.
 */
final readonly class SearchDateRange
{
    public function __construct(
        public Carbon $start,
        public Carbon $end,
    ) {}

    /**
     * Resolve a typed value, or null when it is not a date this understands.
     */
    public static function from(string $value): ?self
    {
        $value = trim($value);

        if (preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/', $value, $match) === 1) {
            // An optional group that never participated is absent from $match
            // entirely, so `isset` is the whole test for "was a month given".
            return self::fromParts(
                (int) $match[1],
                isset($match[2]) ? (int) $match[2] : null,
                isset($match[3]) ? (int) $match[3] : null,
            );
        }

        // Day-first, as written across most of Europe: `31.01.2026`, `31/01/2026`.
        if (preg_match('/^(\d{1,2})[.\/](\d{1,2})[.\/](\d{4})$/', $value, $match) === 1) {
            return self::fromParts((int) $match[3], (int) $match[2], (int) $match[1]);
        }

        return null;
    }

    private static function fromParts(int $year, ?int $month, ?int $day): ?self
    {
        if ($month !== null && ($month < 1 || $month > 12)) {
            return null;
        }

        if ($day !== null && ($day < 1 || $day > 31)) {
            return null;
        }

        try {
            $start = Carbon::create($year, $month ?? 1, $day ?? 1, 0, 0, 0);
        } catch (Throwable) {
            return null;
        }

        // Carbon::create() answers with `false` for an unbuildable date on some
        // versions and throws on others; both mean the same thing here.
        if (! $start instanceof Carbon || ! $start->isValid()) {
            return null;
        }

        $end = match (true) {
            $day !== null => $start->copy()->endOfDay(),
            $month !== null => $start->copy()->endOfMonth(),
            default => $start->copy()->endOfYear(),
        };

        // Carbon rolls an impossible day over into the next month (31 February
        // becomes 3 March), which would silently search the wrong span.
        if ($day !== null && $start->day !== $day) {
            return null;
        }

        return new self($start, $end);
    }
}
