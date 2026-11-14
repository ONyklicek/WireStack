<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use Carbon\CarbonImmutable;

/**
 * A date range named by what it means rather than by its dates — "last 7 days",
 * "this month". Owned here, below every package, because a range is not a
 * table's idea: the table's range DateFilter offers these with ->presets(), and
 * a date field or a widget can name the same ranges without its own list.
 *
 * What the state stores is the name, never the dates it stood for on the day it
 * was picked. That is the point: a saved view or a shared URL of "last 7 days"
 * still means the last seven days next week, where two stored dates would
 * quietly have become a fixed week in the past. The dates are worked out when
 * the query runs ({@see range()}), in the filter's timezone.
 *
 * Weeks start on Monday, as ISO 8601 and most of the world have them.
 */
enum DatePreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisWeek = 'this_week';
    case LastWeek = 'last_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case ThisQuarter = 'this_quarter';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';

    public function label(): string
    {
        return (string) __('wire-core::messages.date_preset_'.$this->value);
    }

    /**
     * The first and last day the preset covers, both inclusive, as `Y-m-d`.
     *
     * @return array{from: string, to: string}
     */
    public function range(CarbonImmutable $now): array
    {
        $today = $now->startOfDay();

        [$from, $to] = match ($this) {
            self::Today => [$today, $today],
            self::Yesterday => [$today->subDay(), $today->subDay()],
            self::Last7Days => [$today->subDays(6), $today],
            self::Last30Days => [$today->subDays(29), $today],
            self::ThisWeek => [$today->startOfWeek(CarbonImmutable::MONDAY), $today->endOfWeek(CarbonImmutable::SUNDAY)],
            self::LastWeek => [$today->subWeek()->startOfWeek(CarbonImmutable::MONDAY), $today->subWeek()->endOfWeek(CarbonImmutable::SUNDAY)],
            self::ThisMonth => [$today->startOfMonth(), $today->endOfMonth()],
            self::LastMonth => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            self::ThisQuarter => [$today->startOfQuarter(), $today->endOfQuarter()],
            self::ThisYear => [$today->startOfYear(), $today->endOfYear()],
            self::LastYear => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
        };

        return ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
    }
}
