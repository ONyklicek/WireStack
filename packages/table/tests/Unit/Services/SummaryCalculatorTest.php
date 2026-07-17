<?php

declare(strict_types=1);

use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Services\SummaryCalculator;
use NyonCode\WireTable\Services\SummaryFormatter;
use NyonCode\WireTable\Support\SummaryFormat;
use NyonCode\WireTable\Support\SummaryTarget;

/*
 * The point of the extraction: sample statistics used to sit in a trait mixed
 * into Column, so median and variance could not be exercised without building a
 * column. Here they take a plain collection and no database.
 */

beforeEach(function () {
    $this->calc = new SummaryCalculator(new SummaryFormatter);
    $this->target = new SummaryTarget('v');
});

test('median of an odd and an even count', function () {
    expect($this->calc->median(collect([5, 1, 3])))->toBe(3.0)
        ->and($this->calc->median(collect([1, 2, 3, 4])))->toBe(2.5);
});

test('sample variance uses an n-1 denominator', function () {
    // Textbook set: sample variance is 32/7.
    expect(round($this->calc->variance(collect([2, 4, 4, 4, 5, 5, 7, 9])), 6))->toBe(round(32 / 7, 6));
});

test('variance of a single value is zero, not a division by zero', function () {
    expect($this->calc->variance(collect([7])))->toBe(0.0)
        ->and($this->calc->variance(collect()))->toBe(0.0);
});

test('an empty collection answers the type its own empty value', function () {
    expect($this->calc->fromCollection(SummaryType::Sum, collect(), $this->target))
        ->toBe(SummaryType::Sum->emptyValue());
});

test('every in-memory summary type', function () {
    $values = collect([1, 1, 2, 3]);

    expect($this->calc->fromCollection(SummaryType::Sum, $values, $this->target))->toBe(7)
        ->and($this->calc->fromCollection(SummaryType::Count, $values, $this->target))->toBe(4)
        ->and($this->calc->fromCollection(SummaryType::DistinctCount, $values, $this->target))->toBe(3)
        ->and($this->calc->fromCollection(SummaryType::Min, $values, $this->target))->toBe(1)
        ->and($this->calc->fromCollection(SummaryType::Max, $values, $this->target))->toBe(3)
        ->and($this->calc->fromCollection(SummaryType::Avg, $values, $this->target))->toBe(1.75)
        ->and($this->calc->fromCollection(SummaryType::First, $values, $this->target))->toBe(1)
        ->and($this->calc->fromCollection(SummaryType::Last, $values, $this->target))->toBe(3)
        ->and($this->calc->fromCollection(SummaryType::Median, $values, $this->target))->toBe(1.5);
});

test('a range is rendered through the formatter it was given', function () {
    $target = new SummaryTarget('v', false, new SummaryFormat(2, ',', ' '));

    expect($this->calc->fromCollection(SummaryType::Range, collect([1000.5, 3000.25]), $target))
        ->toBe('1 000,50 – 3 000,25');
});

test('a batched sum coalesces an empty set to zero', function () {
    // Mirrors Builder::sum(), so the batched path and the per-summary path agree.
    expect($this->calc->normalizeBatched(SummaryType::Sum, null, $this->target))->toBe(0)
        ->and($this->calc->normalizeBatched(SummaryType::Count, '5', $this->target))->toBe(5);
});

test('a batched avg is rounded only for a rollup column', function () {
    // fromAggregateQuery() rounds; the plain query path does not — the batched
    // path has to match whichever it stands in for.
    $rollup = new SummaryTarget('items_sum_total', isAggregate: true);

    expect($this->calc->normalizeBatched(SummaryType::Avg, 1.23456, $rollup))->toBe(1.23)
        ->and($this->calc->normalizeBatched(SummaryType::Avg, 1.23456, $this->target))->toBe(1.23456)
        ->and($this->calc->normalizeBatched(SummaryType::Avg, null, $rollup))->toBeNull();
});

test('a batched range over an empty rollup set is the placeholder', function () {
    $rollup = new SummaryTarget('items_sum_total', isAggregate: true);

    expect($this->calc->normalizeBatched(SummaryType::Range, ['min' => null, 'max' => null], $rollup))
        ->toBe('–')
        ->and($this->calc->normalizeBatched(SummaryType::Range, ['min' => 1, 'max' => 9], $rollup))
        ->toBe('1 – 9');
});
