<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use NyonCode\WireCore\Core\Support\Trans;

/**
 * Built-in summary aggregate types.
 *
 * Canonical owner of per-type semantics: default footer labels, count-like
 * formatting, SQL portability, and empty-set results. `summarize()` accepts
 * both the enum case and its string value:
 *
 *   ->summarize(SummaryType::Median)
 *   ->summarize('median')              // normalized to the enum
 */
enum SummaryType: string
{
    case Sum = 'sum';
    case Avg = 'avg';
    case Count = 'count';
    case DistinctCount = 'distinctCount';
    case Min = 'min';
    case Max = 'max';
    case Range = 'range';
    case Median = 'median';
    case Variance = 'variance';
    case Stddev = 'stddev';
    case First = 'first';
    case Last = 'last';

    /**
     * Default (translated) footer label for the type.
     */
    public function label(): string
    {
        return Trans::get('wire-table::messages.'.$this->translationKey());
    }

    public function translationKey(): string
    {
        return match ($this) {
            self::Sum => 'summary_sum',
            self::Avg => 'summary_avg',
            self::Count => 'summary_count',
            self::DistinctCount => 'summary_distinct',
            self::Min => 'summary_min',
            self::Max => 'summary_max',
            self::Range => 'summary_range',
            self::Median => 'summary_median',
            self::Variance => 'summary_variance',
            self::Stddev => 'summary_stddev',
            self::First => 'summary_first',
            self::Last => 'summary_last',
        };
    }

    /**
     * Whole-number counts — never reformatted with decimals or prefix/suffix.
     */
    public function isCount(): bool
    {
        return $this === self::Count || $this === self::DistinctCount;
    }

    /**
     * Whether the aggregate runs natively in SQL at 'query' scope. The rest
     * (statistical/positional types) pluck the column and compute in PHP.
     */
    public function isSqlNative(): bool
    {
        return match ($this) {
            self::Median, self::Variance, self::Stddev, self::First, self::Last => false,
            default => true,
        };
    }

    /**
     * Result for an empty record set.
     */
    public function emptyValue(): mixed
    {
        return match ($this) {
            self::Sum, self::Count, self::DistinctCount => 0,
            self::Range => '–',
            default => null,
        };
    }
}
