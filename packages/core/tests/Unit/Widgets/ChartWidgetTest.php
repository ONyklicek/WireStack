<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\ChartWidget;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    $widget = ChartWidget::make();

    expect($widget)->toBeInstanceOf(ChartWidget::class);
});

// ─── Type ────────────────────────────────────────────────────────────────────

it('defaults to line type', function () {
    expect(ChartWidget::make()->getType())->toBe('line');
});

it('can set chart type', function () {
    expect(ChartWidget::make()->type('bar')->getType())->toBe('bar')
        ->and(ChartWidget::make()->type('pie')->getType())->toBe('pie')
        ->and(ChartWidget::make()->type('doughnut')->getType())->toBe('doughnut');
});

// ─── Datasets and Labels ─────────────────────────────────────────────────────

it('can set static datasets and labels', function () {
    $datasets = [
        ['label' => 'Revenue', 'data' => [100, 200, 300]],
    ];
    $labels = ['Jan', 'Feb', 'Mar'];

    $widget = ChartWidget::make()
        ->datasets($datasets)
        ->labels($labels);

    expect($widget->getDatasets())->toBe($datasets)
        ->and($widget->getLabels())->toBe($labels);
});

it('can set dynamic datasets via closure', function () {
    $widget = ChartWidget::make()
        ->datasets(fn (?string $filter) => [
            ['label' => $filter ?? 'All', 'data' => [1, 2, 3]],
        ])
        ->activeFilter('2026');

    $datasets = $widget->getDatasets();

    expect($datasets)->toHaveCount(1)
        ->and($datasets[0]['label'])->toBe('2026');
});

it('can set dynamic labels via closure', function () {
    $widget = ChartWidget::make()
        ->labels(fn (?string $filter) => ['Q1', 'Q2', 'Q3', 'Q4']);

    expect($widget->getLabels())->toHaveCount(4);
});

// ─── Filter ──────────────────────────────────────────────────────────────────

it('has no filter by default', function () {
    $widget = ChartWidget::make();

    expect($widget->hasFilter())->toBeFalse()
        ->and($widget->getFilterOptions())->toBeNull();
});

it('can set filter options', function () {
    $widget = ChartWidget::make()->filter([
        '2025' => 'Year 2025',
        '2026' => 'Year 2026',
    ], '2026');

    expect($widget->hasFilter())->toBeTrue()
        ->and($widget->getFilterOptions())->toHaveCount(2)
        ->and($widget->getActiveFilter())->toBe('2026');
});

it('defaults active filter to first option', function () {
    $widget = ChartWidget::make()->filter([
        'week' => 'This Week',
        'month' => 'This Month',
    ]);

    expect($widget->getActiveFilter())->toBe('week');
});
