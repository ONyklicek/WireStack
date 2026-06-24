<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;

// Regression: filter views must never pass an array into a string/echo context
// (htmlspecialchars TypeError). Array values can reach the views via array
// defaults, multi/single mismatch, or stale URL/session state.

it('renders a single select filter when state holds an array value', function () {
    $filter = SelectFilter::make('status')->options(['paid' => 'Paid', 'pending' => 'Pending']);

    expect($filter->render(['value' => ['paid', 'pending']]))->toBeString()
        ->and($filter->render(['paid', 'pending']))->toBeString();
});

it('marks matching options selected for both single and multiple selects', function () {
    $single = SelectFilter::make('status')->options(['paid' => 'Paid', 'pending' => 'Pending']);
    expect($single->render('paid'))
        ->toMatch('/value="paid"\s+selected/')
        ->not->toMatch('/value="pending"\s+selected/');

    $multiple = SelectFilter::make('status')->multiple()->options(['paid' => 'Paid', 'pending' => 'Pending']);
    expect($multiple->render(['value' => ['paid', 'pending']]))
        ->toMatch('/value="paid"\s+selected/')
        ->toMatch('/value="pending"\s+selected/');
});

it('renders a base text filter when state holds a nested array value', function () {
    expect(Filter::make('q')->render(['value' => ['a', 'b']]))->toBeString();
});

it('renders single date and number-range filters with array-shaped scalars', function () {
    expect(DateFilter::make('created')->render(['value' => ['2026-01-01']]))->toBeString()
        ->and(NumberRangeFilter::make('total')->render(['min' => ['x'], 'max' => 5]))->toBeString();
});

it('renders a select filter with an array default on a single-value filter', function () {
    $filter = SelectFilter::make('status')
        ->options(['paid' => 'Paid'])
        ->default(['paid', 'pending']);

    expect($filter->render($filter->wrapValue($filter->getDefault())))->toBeString();
});
