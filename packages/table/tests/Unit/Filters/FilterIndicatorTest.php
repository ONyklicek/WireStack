<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;

// ─── Base filter ─────────────────────────────────────────────────────────────

it('builds a label: value indicator for an active base filter', function () {
    $filter = Filter::make('status')->label('Status');

    expect($filter->getIndicator(['value' => 'active']))->toBe('Status: active');
});

it('returns null for inactive values', function () {
    $filter = Filter::make('status');

    expect($filter->getIndicator(null))->toBeNull()
        ->and($filter->getIndicator(['value' => null]))->toBeNull()
        ->and($filter->getIndicator(['value' => '']))->toBeNull()
        ->and($filter->getIndicator(['value' => []]))->toBeNull();
});

it('accepts raw scalar values like programmatic callers do', function () {
    $filter = Filter::make('status')->label('Status');

    expect($filter->getIndicator('active'))->toBe('Status: active');
});

it('uses a fixed string indicator when configured', function () {
    $filter = Filter::make('status')->indicator('Only active');

    expect($filter->getIndicator(['value' => 'x']))->toBe('Only active')
        ->and($filter->getIndicator(null))->toBeNull();
});

it('uses a closure indicator receiving the unwrapped value', function () {
    $filter = Filter::make('status')->indicator(fn ($value, Filter $f) => strtoupper($value));

    expect($filter->getIndicator(['value' => 'active']))->toBe('ACTIVE');
});

it('hides the chip when the closure indicator returns null or an empty string', function () {
    $nullIndicator = Filter::make('status')->indicator(fn () => null);
    $emptyIndicator = Filter::make('status')->indicator(fn () => '');

    expect($nullIndicator->getIndicator(['value' => 'x']))->toBeNull()
        ->and($emptyIndicator->getIndicator(['value' => 'x']))->toBeNull();
});

// ─── SelectFilter ────────────────────────────────────────────────────────────

it('maps select values to option labels', function () {
    $filter = SelectFilter::make('status')
        ->label('Status')
        ->options(['active' => 'Active', 'archived' => 'Archived']);

    expect($filter->getIndicator(['value' => 'active']))->toBe('Status: Active');
});

it('joins option labels for multiple select values', function () {
    $filter = SelectFilter::make('status')
        ->label('Status')
        ->multiple()
        ->options(['active' => 'Active', 'archived' => 'Archived']);

    expect($filter->getIndicator(['value' => ['active', 'archived']]))->toBe('Status: Active, Archived');
});

it('falls back to the raw value for unknown select options', function () {
    $filter = SelectFilter::make('status')->label('Status')->options(['a' => 'A']);

    expect($filter->getIndicator(['value' => 'b']))->toBe('Status: b');
});

// ─── TernaryFilter ───────────────────────────────────────────────────────────

it('shows the true and false labels for ternary values', function () {
    $filter = TernaryFilter::make('paid')
        ->label('Paid')
        ->trueLabel('Paid')
        ->falseLabel('Unpaid');

    expect($filter->getIndicator(['value' => '1']))->toBe('Paid: Paid')
        ->and($filter->getIndicator(['value' => '0']))->toBe('Paid: Unpaid')
        ->and($filter->getIndicator(['value' => '']))->toBeNull();
});

// ─── NumberRangeFilter ───────────────────────────────────────────────────────

it('renders number range bounds', function () {
    $filter = NumberRangeFilter::make('price')->label('Price');

    expect($filter->getIndicator(['min' => '10', 'max' => '100']))->toBe('Price: 10 – 100')
        ->and($filter->getIndicator(['min' => '10', 'max' => '']))->toBe('Price: ≥ 10')
        ->and($filter->getIndicator(['min' => '', 'max' => '100']))->toBe('Price: ≤ 100')
        ->and($filter->getIndicator(['min' => '', 'max' => '']))->toBeNull();
});

// ─── DateFilter ──────────────────────────────────────────────────────────────

it('renders a single date value', function () {
    $filter = DateFilter::make('created_at')->label('Created');

    expect($filter->getIndicator(['value' => '2026-06-11']))->toBe('Created: 2026-06-11');
});

it('renders date range bounds', function () {
    $filter = DateFilter::make('created_at')->label('Created')->range();

    expect($filter->getIndicator(['from' => '2026-06-01', 'to' => '2026-06-30']))
        ->toBe('Created: 2026-06-01 – 2026-06-30')
        ->and($filter->getIndicator(['from' => '2026-06-01', 'to' => '']))
        ->toBe('Created: '.$filter->getFromLabel().' 2026-06-01')
        ->and($filter->getIndicator(['from' => '', 'to' => '']))->toBeNull();
});

it('renders month mode values as a translated month name', function () {
    $filter = DateFilter::make('billed_at')->label('Billed')->month();

    expect($filter->getIndicator(['value' => '2026-05']))->toBe('Billed: May 2026');
});
