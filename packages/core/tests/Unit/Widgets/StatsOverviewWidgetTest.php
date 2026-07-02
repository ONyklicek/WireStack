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

// ─── Responsive grid (regression: inline repeat() ignored the viewport) ───

it('renders a responsive column grid instead of an inline grid-template style', function () {
    $html = StatsOverviewWidget::make()
        ->columns(3)
        ->stats([
            Stat::make('Open', '18'),
            Stat::make('Queued', '6'),
            Stat::make('Hooks', '34'),
        ])
        ->toHtml();

    expect($html)
        ->toContain('grid-cols-1')
        ->toContain('sm:grid-cols-2 lg:grid-cols-3')
        ->not->toContain('grid-template-columns');
});

it('collapses to one column by default on mobile for every configured count', function () {
    foreach ([2 => 'sm:grid-cols-2', 4 => 'sm:grid-cols-2 lg:grid-cols-4'] as $columns => $expected) {
        $html = StatsOverviewWidget::make()
            ->columns($columns)
            ->stats([Stat::make('A', '1')])
            ->toHtml();

        expect($html)->toContain('grid-cols-1')->toContain($expected);
    }
});
