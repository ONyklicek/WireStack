<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\ChartItem;

it('is created with a label via make()', function () {
    expect(ChartItem::make('CPU')->getLabel())->toBe('CPU');
});

it('stores a numeric value as float', function () {
    expect(ChartItem::make('A')->value(125000)->getValue())->toBe(125000.0);
});

it('returns the explicit formatted value when set', function () {
    expect(ChartItem::make('A')->value(98500)->formattedValue('98 500 Kč')->getFormattedValue())
        ->toBe('98 500 Kč');
});

it('falls back to the raw value when no formatted value is set', function () {
    expect(ChartItem::make('A')->value(72)->getFormattedValue())->toBe('72');
});

it('defaults to the primary color', function () {
    expect(ChartItem::make('A')->getColor())->toBe('primary');
});

it('keeps an explicit color', function () {
    expect(ChartItem::make('A')->color('green')->getColor())->toBe('green');
});

it('accepts a percentage within range', function () {
    $item = ChartItem::make('A')->percentage(55);

    expect($item->hasPercentage())->toBeTrue()
        ->and($item->getPercentage())->toBe(55.0);
});

it('rejects a percentage above 100', function () {
    ChartItem::make('A')->percentage(120);
})->throws(InvalidArgumentException::class);

it('rejects a negative percentage', function () {
    ChartItem::make('A')->percentage(-5);
})->throws(InvalidArgumentException::class);

it('stores an icon', function () {
    expect(ChartItem::make('CPU')->icon('cpu-chip')->getIcon())->toBe('cpu-chip');
});
