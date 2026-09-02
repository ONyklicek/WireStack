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

it('draws the heading and description it was given', function () {
    // Asserted on the markup, not on the getters above: this view drew neither
    // for as long as it existed, and the getter test passed the whole time —
    // `->heading()` was API that did nothing. Found by composing a dashboard out
    // of these widgets (V2.6 step 3), where the headings simply were not there.
    $html = StatsOverviewWidget::make()
        ->heading('Overview')
        ->description('Key metrics')
        ->stats([Stat::make('Revenue', '1.2M')])
        ->toHtml();

    expect($html)->toContain('Overview')
        ->toContain('Key metrics')
        ->toContain('Revenue');
});

it('draws no heading block when it was given neither', function () {
    $html = StatsOverviewWidget::make()->stats([Stat::make('Revenue', '1.2M')])->toHtml();

    expect($html)->not->toContain('<h3');
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

// ─── Sparkline ───────────────────────────────────────────────────────────────

it('draws a stat chart through the shared sparkline geometry', function () {
    // The polyline used to be computed by an @php block in this template. It is
    // Foundation\View\Sparkline now, and a table's MetricColumn draws the same
    // curve — so this asserts the widget still emits it, and emits the geometry
    // the owner produces rather than a second copy of the arithmetic.
    $html = StatsOverviewWidget::make()
        ->stats([Stat::make('Revenue', '45 231')->chart([1, 5, 3])])
        ->toHtml();

    expect($html)->toContain('<polyline')
        ->toContain('viewBox="0 0 20 30"')
        ->toContain('points="0,30 10,2 20,16"');
});

it('draws no chart for a stat that has no series', function () {
    $html = StatsOverviewWidget::make()
        ->stats([Stat::make('Revenue', '45 231')])
        ->toHtml();

    expect($html)->not->toContain('<polyline');
});

it('reaches the top of the box when a stat series tops out at zero', function () {
    // The template guarded a maximum of 0 as if it were the divisor, which
    // stretched the range and squashed the curve. A burndown hitting its target
    // is exactly that series, and it now reaches the top.
    $html = StatsOverviewWidget::make()
        ->stats([Stat::make('Remaining', '0')->chart([-5, -2, 0])])
        ->toHtml();

    expect($html)->toContain('points="0,30 10,13.2 20,2"');
});
