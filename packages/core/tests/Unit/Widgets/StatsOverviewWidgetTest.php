<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Widget;

// ─── Factory ─────────────────────────────────────────────────────────────────

it('can be created via static make()', function () {
    $widget = StatsOverviewWidget::make();

    expect($widget)->toBeInstanceOf(StatsOverviewWidget::class)
        ->and($widget)->toBeInstanceOf(Widget::class);
});

// ─── Stats ───────────────────────────────────────────────────────────────────

it('accepts an array of stats', function () {
    $widget = StatsOverviewWidget::make()->stats([
        Stat::make('Revenue', '$45,231'),
        Stat::make('Orders', '1,234'),
        Stat::make('Customers', '567'),
    ]);

    expect($widget->getStats())->toHaveCount(3);
});

// ─── Grid Columns ────────────────────────────────────────────────────────────

it('defaults to 3 columns', function () {
    $widget = StatsOverviewWidget::make();

    expect($widget->getGridColumns())->toBe(3);
});

it('can set grid columns', function () {
    $widget = StatsOverviewWidget::make()->columns(4);

    expect($widget->getGridColumns())->toBe(4);
});

it('clamps grid columns between 1 and 4', function () {
    expect(StatsOverviewWidget::make()->columns(0)->getGridColumns())->toBe(1)
        ->and(StatsOverviewWidget::make()->columns(5)->getGridColumns())->toBe(4);
});

// ─── Widget Base ─────────────────────────────────────────────────────────────

it('supports heading and description', function () {
    $widget = StatsOverviewWidget::make()
        ->heading('Overview')
        ->description('Key metrics');

    expect($widget->getHeading())->toBe('Overview')
        ->and($widget->getDescription())->toBe('Key metrics');
});

it('supports column span', function () {
    $widget = StatsOverviewWidget::make()->columnSpanFull();

    expect($widget->getColumnSpan())->toBe('full');
});

it('supports polling', function () {
    $widget = StatsOverviewWidget::make()->pollingInterval('30s');

    expect($widget->isPolling())->toBeTrue()
        ->and($widget->getPollingInterval())->toBe('30s')
        ->and($widget->getPollingDirective())->toBe('wire:poll.30s.visible');
});

it('supports lazy loading', function () {
    $widget = StatsOverviewWidget::make()->lazy();

    expect($widget->isLazy())->toBeTrue();
});

it('supports visibility', function () {
    $widget = StatsOverviewWidget::make()->visible(false);

    expect($widget->isVisible())->toBeFalse();
});
