<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\DateFilter;

it('can be created', function () {
    expect(DateFilter::make('created_at'))->toBeInstanceOf(DateFilter::class);
});

// ─── Range ──────────────────────────────────────────────────────────────────

it('is not range by default', function () {
    expect(DateFilter::make('created_at')->isRange())->toBeFalse();
});

it('can be set to range', function () {
    expect(DateFilter::make('created_at')->range()->isRange())->toBeTrue();
});

// ─── Min/Max Date ───────────────────────────────────────────────────────────

it('has no min/max date by default', function () {
    $filter = DateFilter::make('created_at');

    expect($filter->getMinDate())->toBeNull()
        ->and($filter->getMaxDate())->toBeNull();
});

it('can set min and max date', function () {
    $filter = DateFilter::make('created_at')
        ->minDate('2024-01-01')
        ->maxDate('2024-12-31');

    expect($filter->getMinDate())->toBe('2024-01-01')
        ->and($filter->getMaxDate())->toBe('2024-12-31');
});

// ─── Labels ─────────────────────────────────────────────────────────────────

it('has default labels from translation', function () {
    $filter = DateFilter::make('created_at');

    expect($filter->getFromLabel())->toBe('From')
        ->and($filter->getToLabel())->toBe('To');
});

it('can set custom labels', function () {
    $filter = DateFilter::make('created_at')
        ->fromLabel('From')
        ->toLabel('To');

    expect($filter->getFromLabel())->toBe('From')
        ->and($filter->getToLabel())->toBe('To');
});
