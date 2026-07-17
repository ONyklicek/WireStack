<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Services\SummaryCalculator;

/**
 * What a summary is computed over.
 *
 * Everything {@see SummaryCalculator} needs to know
 * about the column it is summarizing, so the calculator never holds one.
 *
 * `$isAggregate` is load-bearing rather than cosmetic: a rollup column's value
 * is a `withSum`/`withCount` subselect alias, not a real table column, so it can
 * only be aggregated by wrapping the query as a derived table. Getting this
 * wrong does not error — it silently sums the wrong thing.
 */
final readonly class SummaryTarget
{
    public function __construct(
        public string $column,
        public bool $isAggregate = false,
        public SummaryFormat $format = new SummaryFormat,
    ) {}
}
