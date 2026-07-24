<?php

declare(strict_types=1);

use NyonCode\WireForms\Components\DateTimePicker;
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

// ─── Month Mode ─────────────────────────────────────────────────────────────

it('is not month mode by default', function () {
    $filter = DateFilter::make('created_at');

    expect($filter->isMonth())->toBeFalse();
});

it('can be set to month mode', function () {
    $filter = DateFilter::make('created_at')->month();

    expect($filter->isMonth())->toBeTrue();
});

it('bypasses the planner in every mode so a date-truncated comparison always routes through apply()', function () {
    // Single-date state is a scalar, so a DateFilter that did not bypass the
    // planner would be neither routed to apply() nor turned into a planner
    // definition (toPlannerDefinitions() is always []) — a silent no-op.
    expect(DateFilter::make('created_at')->bypassesPlanner())->toBeTrue()
        ->and(DateFilter::make('created_at')->range()->bypassesPlanner())->toBeTrue()
        ->and(DateFilter::make('created_at')->month()->bypassesPlanner())->toBeTrue();
});

it('renders a month picker form field in month mode', function () {
    $fields = DateFilter::make('created_at')->month()->getFormFields();

    expect($fields)->toHaveCount(1)
        ->and($fields[0])->toBeInstanceOf(DateTimePicker::class)
        ->and($fields[0]->getMode())->toBe('month')
        ->and($fields[0]->getNativeInputType())->toBe('month')
        ->and($fields[0]->isNative())->toBeTrue();
});
