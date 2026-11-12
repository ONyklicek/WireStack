<?php

declare(strict_types=1);

use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\NumberRangeFilter;

it('renders NumberRangeFilter through the shared form-field template', function () {
    $html = NumberRangeFilter::make('price')->render(['min' => 10, 'max' => 100]);

    expect($html)->toContain('wire:model.live.debounce.500ms="tableState.filters.price.min"')
        ->and($html)->toContain('wire:model.live.debounce.500ms="tableState.filters.price.max"')
        ->and($html)->toContain('type="number"')
        ->and($html)->toContain('value="10"')
        ->and($html)->toContain('value="100"');
});

it('draws a DateFilter range in the panel with the forms DateRangePicker', function () {
    $html = DateFilter::make('created_at')
        ->range()
        ->render(['from' => '2024-01-01', 'to' => '2024-12-31']);

    expect($html)->toContain('wireDateRangePicker(')
        ->and($html)->toContain("entangle('tableState.filters.created_at.from')")
        ->and($html)->toContain("entangle('tableState.filters.created_at.to')")
        ->and($html)->not->toContain('type="date"');
});

it('draws a single DateFilter in the panel with the forms DateTimePicker', function () {
    $html = DateFilter::make('created_at')->render(['value' => '2024-06-15']);

    expect($html)->toContain('form-datetime-tableState.filters.created_at.value')
        ->and($html)->not->toContain('tableState.filters.created_at.from');

    $withOperators = DateFilter::make('created_at')->operators()->render(['value' => null, 'operator' => 'is_empty']);
    expect($withOperators)->toContain('filter-created_at-operator')
        ->and($withOperators)->not->toContain('form-datetime-tableState.filters.created_at.value');
});

it('keeps the plain month input for a month DateFilter', function () {
    expect(DateFilter::make('created_at')->month()->render(['value' => '2024-06']))->toContain('tableState.filters.created_at.value');
});

it('returns an empty string when the filter is not viewable', function () {
    $filter = NumberRangeFilter::make('price')->hidden();

    expect($filter->render())->toBe('');
});
