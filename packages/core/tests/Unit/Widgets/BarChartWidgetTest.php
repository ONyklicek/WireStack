<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;

// ─── Factory & defaults ──────────────────────────────────────────────────────

it('can be created via static make()', function () {
    expect(BarChartWidget::make())->toBeInstanceOf(BarChartWidget::class);
});

it('has sensible defaults', function () {
    $widget = BarChartWidget::make();

    expect($widget->getType())->toBe('vertical')
        ->and($widget->getVariant())->toBe('default')
        ->and($widget->getItems())->toBe([])
        ->and($widget->shouldShowGrid())->toBeFalse()
        ->and($widget->shouldShowMenu())->toBeFalse()
        ->and($widget->getMaxValue())->toBeNull()
        ->and($widget->getHeight())->toBe(240)
        ->and($widget->getRounded())->toBe('2xl');
});

// ─── Type validation ─────────────────────────────────────────────────────────

it('accepts allowed types', function () {
    expect(BarChartWidget::make()->type('vertical')->getType())->toBe('vertical')
        ->and(BarChartWidget::make()->type('horizontal')->getType())->toBe('horizontal');
});

it('rejects an invalid type', function () {
    BarChartWidget::make()->type('diagonal');
})->throws(InvalidArgumentException::class);

// ─── Variant validation ──────────────────────────────────────────────────────

it('accepts allowed variants', function () {
    expect(BarChartWidget::make()->variant('finance')->getVariant())->toBe('finance')
        ->and(BarChartWidget::make()->variant('system')->getVariant())->toBe('system')
        ->and(BarChartWidget::make()->variant('default')->getVariant())->toBe('default');
});

it('rejects an invalid variant', function () {
    BarChartWidget::make()->variant('pie');
})->throws(InvalidArgumentException::class);

// ─── Items ───────────────────────────────────────────────────────────────────

it('accepts an array of ChartItem instances', function () {
    $widget = BarChartWidget::make()->items([
        ChartItem::make('CPU')->value(72),
        ChartItem::make('RAM')->value(54),
    ]);

    expect($widget->getItems())->toHaveCount(2)
        ->and($widget->getItems()[0])->toBeInstanceOf(ChartItem::class);
});

it('rejects non-ChartItem entries', function () {
    BarChartWidget::make()->items(['not-an-item']);
})->throws(InvalidArgumentException::class);

// ─── Toggles & options ───────────────────────────────────────────────────────

it('toggles grid and menu', function () {
    expect(BarChartWidget::make()->showGrid()->shouldShowGrid())->toBeTrue()
        ->and(BarChartWidget::make()->showGrid(false)->shouldShowGrid())->toBeFalse()
        ->and(BarChartWidget::make()->showMenu()->shouldShowMenu())->toBeTrue();
});

it('clamps height to a positive value', function () {
    expect(BarChartWidget::make()->height(0)->getHeight())->toBe(1)
        ->and(BarChartWidget::make()->height(320)->getHeight())->toBe(320);
});

it('can set and clear the max value', function () {
    expect(BarChartWidget::make()->maxValue(200)->getMaxValue())->toBe(200.0)
        ->and(BarChartWidget::make()->maxValue(null)->getMaxValue())->toBeNull();
});

// ─── Percentage resolution ───────────────────────────────────────────────────

it('uses an explicit percentage when set', function () {
    $widget = BarChartWidget::make();
    $item = ChartItem::make('CPU')->value(999)->percentage(72);

    expect($widget->percentageFor($item))->toBe(72.0);
});

it('scales against an absolute max value', function () {
    $widget = BarChartWidget::make()->maxValue(200);

    expect($widget->percentageFor(ChartItem::make('A')->value(50)))->toBe(25.0);
});

it('auto-scales relative to the largest item value', function () {
    $widget = BarChartWidget::make()->items([
        ChartItem::make('A')->value(50),
        ChartItem::make('B')->value(100),
    ]);

    expect($widget->percentageFor($widget->getItems()[0]))->toBe(50.0)
        ->and($widget->percentageFor($widget->getItems()[1]))->toBe(100.0);
});

it('clamps value-based percentages into 0–100', function () {
    $widget = BarChartWidget::make()->maxValue(100);

    expect($widget->percentageFor(ChartItem::make('Over')->value(250)))->toBe(100.0);
});

it('returns 0 for an all-zero data set', function () {
    $widget = BarChartWidget::make()->items([
        ChartItem::make('A')->value(0),
        ChartItem::make('B')->value(0),
    ]);

    expect($widget->percentageFor($widget->getItems()[0]))->toBe(0.0);
});

// ─── Safe color mapping ──────────────────────────────────────────────────────

it('maps item colors to safe gradient fill classes', function () {
    $widget = BarChartWidget::make();

    expect($widget->fillClassesFor(ChartItem::make('A')->color('blue')))
        ->toBe('from-blue-500 to-blue-600')
        ->and($widget->fillClassesFor(ChartItem::make('B')->color('green')))
        ->toBe('from-green-500 to-green-600')
        ->and($widget->fillClassesFor(ChartItem::make('C')->color('orange')))
        ->toBe('from-orange-500 to-orange-600')
        ->and($widget->fillClassesFor(ChartItem::make('D')->color('purple')))
        ->toBe('from-purple-500 to-purple-600')
        ->and($widget->fillClassesFor(ChartItem::make('E')->color('gray')))
        ->toBe('from-slate-400 to-slate-500');
});

it('maps item colors to matching literal accent text classes', function () {
    $widget = BarChartWidget::make();

    expect($widget->textClassesFor(ChartItem::make('A')->color('blue')))
        ->toBe('text-blue-600 dark:text-blue-400')
        ->and($widget->textClassesFor(ChartItem::make('B')->color('green')))
        ->toBe('text-green-600 dark:text-green-400');
});

it('falls back to the default gradient for an unknown color', function () {
    expect(BarChartWidget::getGradientFillClasses('totally-made-up'))
        ->toBe('from-primary-500 to-primary-600');
});

// ─── Rendering ───────────────────────────────────────────────────────────────

it('renders the card with heading and value', function () {
    $html = BarChartWidget::make()
        ->heading('Revenue overview')
        ->variant('finance')
        ->items([
            ChartItem::make('01 / 2024')->value(125000)->formattedValue('125 000 Kč')->color('blue')->percentage(70),
        ])
        ->toHtml();

    expect($html)->toContain('Revenue overview')
        ->and($html)->toContain('125 000 Kč')
        ->and($html)->toContain('--value: 70%')
        ->and($html)->toContain('from-blue-500 to-blue-600');
});

// ─── Vertical (rotated) per-bar labels ────────────────────────────────────────

it('does not use vertical labels by default and can enable them', function () {
    expect(BarChartWidget::make()->hasVerticalLabels())->toBeFalse()
        ->and(BarChartWidget::make()->verticalLabels()->hasVerticalLabels())->toBeTrue()
        ->and(BarChartWidget::make()->verticalLabels(false)->hasVerticalLabels())->toBeFalse();
});

it('renders each bar label rotated vertically on both vertical variants when enabled', function () {
    $item = ChartItem::make('Very Long Package Name')->value(60)->formattedValue('60%')->color('blue')->percentage(60);

    $finance = BarChartWidget::make()->variant('finance')->verticalLabels()->items([$item])->toHtml();
    expect($finance)->toContain('Very Long Package Name')
        ->and($finance)->toContain('writing-mode: vertical-rl');

    $system = BarChartWidget::make()->verticalLabels()->items([$item])->toHtml();
    expect($system)->toContain('Very Long Package Name')
        ->and($system)->toContain('writing-mode: vertical-rl');
});

it('keeps labels horizontal (no rotation) by default', function () {
    $html = BarChartWidget::make()->variant('finance')
        ->items([ChartItem::make('CPU')->value(60)->formattedValue('60%')->color('blue')->percentage(60)])
        ->toHtml();

    expect($html)->toContain('CPU')
        ->and($html)->not->toContain('writing-mode: vertical-rl');
});

// ─── Card radius & partial selection ─────────────────────────────────────────

it('maps the rounded value to a safe card radius class', function () {
    expect(BarChartWidget::make()->getCardRadiusClass())->toBe('rounded-2xl')
        ->and(BarChartWidget::make()->rounded('none')->getCardRadiusClass())->toBe('rounded-none')
        ->and(BarChartWidget::make()->rounded('lg')->getCardRadiusClass())->toBe('rounded-lg')
        ->and(BarChartWidget::make()->rounded('full')->getCardRadiusClass())->toBe('rounded-3xl')
        ->and(BarChartWidget::make()->rounded('bogus')->getCardRadiusClass())->toBe('rounded-2xl');
});

it('selects the rendering partial from type and variant', function () {
    expect(BarChartWidget::make()->getPartialName())->toBe('vertical-system')
        ->and(BarChartWidget::make()->variant('finance')->getPartialName())->toBe('vertical-finance')
        ->and(BarChartWidget::make()->type('horizontal')->getPartialName())->toBe('horizontal-system')
        ->and(BarChartWidget::make()->type('horizontal')->variant('finance')->getPartialName())->toBe('vertical-finance');
});
