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

it('uses the shared form-field template for DateFilter in range mode', function () {
    $html = DateFilter::make('created_at')
        ->range()
        ->render(['from' => '2024-01-01', 'to' => '2024-12-31']);

    expect($html)->toContain('wire:model.live.debounce.500ms="tableState.filters.created_at.from"')
        ->and($html)->toContain('wire:model.live.debounce.500ms="tableState.filters.created_at.to"')
        ->and($html)->toContain('type="date"');
});

it('renders DateFilter single mode through the shared form-field template', function () {
    $html = DateFilter::make('created_at')->render(['value' => '2024-06-15']);

    expect($html)->toContain('wire:model.live.debounce.500ms="tableState.filters.created_at.value"')
        ->and($html)->toContain('type="date"')
        ->and($html)->not->toContain('tableState.filters.created_at.from')
        ->and($html)->not->toContain('tableState.filters.created_at.to');
});

it('returns an empty string when the filter is not viewable', function () {
    $filter = NumberRangeFilter::make('price')->hidden();

    expect($filter->render())->toBe('');
});
