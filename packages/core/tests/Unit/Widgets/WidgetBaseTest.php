<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\CustomWidget;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\TableWidget;
use NyonCode\WireCore\Widgets\Widget;

// ─── CustomWidget ────────────────────────────────────────────────────────────

it('creates a custom widget with a view', function () {
    $widget = CustomWidget::make()->view('dashboard.my-widget');

    expect($widget)->toBeInstanceOf(Widget::class)
        ->and($widget->getCustomView())->toBe('dashboard.my-widget');
});

it('renders a custom widget with custom view data', function () {
    View::addNamespace('wire-core-test', __DIR__.'/../../Fixtures/views');

    $html = CustomWidget::make()
        ->view('wire-core-test::custom-widget')
        ->viewData(['message' => 'Hello from custom data'])
        ->render()
        ->render();

    expect($html)
        ->toContain('wire-core-test-widget')
        ->toContain('Hello from custom data');
});

it('renders a stats overview widget with stats data', function () {
    $html = StatsOverviewWidget::make()
        ->stats([
            Stat::make('Revenue', '$100'),
        ])
        ->render()
        ->render();

    expect($html)
        ->toContain('wire-stats-overview')
        ->toContain('Revenue')
        ->toContain('$100');
});

it('renders a chart widget with chart data', function () {
    $html = ChartWidget::make()
        ->type('bar')
        ->labels(['Jan'])
        ->datasets([
            ['label' => 'Revenue', 'data' => [100]],
        ])
        ->render()
        ->render();

    expect($html)
        ->toContain('wire-chart-widget')
        ->toContain('Revenue')
        ->toContain('Jan');
});

// ─── TableWidget ─────────────────────────────────────────────────────────────

it('creates a table widget with callback', function () {
    $callback = fn () => null;
    $widget = TableWidget::make()->table($callback);

    expect($widget)->toBeInstanceOf(Widget::class)
        ->and($widget->getTableCallback())->toBe($callback);
});

// ─── HasPolling ──────────────────────────────────────────────────────────────

it('is not polling by default', function () {
    $widget = CustomWidget::make();

    expect($widget->isPolling())->toBeFalse()
        ->and($widget->getPollingInterval())->toBeNull()
        ->and($widget->getPollingDirective())->toBeNull();
});

it('can enable polling', function () {
    $widget = CustomWidget::make()->pollingInterval('15s');

    expect($widget->isPolling())->toBeTrue()
        ->and($widget->getPollingInterval())->toBe('15s');
});

it('generates correct polling directive', function () {
    $widget = CustomWidget::make()->pollingInterval('30s');

    expect($widget->getPollingDirective())->toBe('wire:poll.30s.visible');
});

it('can disable visible-only polling', function () {
    $widget = CustomWidget::make()
        ->pollingInterval('10s')
        ->pollingOnlyVisible(false);

    expect($widget->getPollingDirective())->toBe('wire:poll.10s');
});

// ─── HasColumnSpan ───────────────────────────────────────────────────────────

it('has no column span by default', function () {
    expect(CustomWidget::make()->getColumnSpan())->toBeNull();
});

it('can set column span', function () {
    expect(CustomWidget::make()->columnSpan(2)->getColumnSpan())->toBe(2);
});

it('can set full column span', function () {
    expect(CustomWidget::make()->columnSpanFull()->getColumnSpan())->toBe('full');
});

// ─── Heading and Description ─────────────────────────────────────────────────

it('has no heading by default', function () {
    expect(CustomWidget::make()->getHeading())->toBeNull();
});

it('supports heading and description', function () {
    $widget = CustomWidget::make()
        ->heading('My Widget')
        ->description('Some description');

    expect($widget->getHeading())->toBe('My Widget')
        ->and($widget->getDescription())->toBe('Some description');
});

// ─── Lazy Loading ────────────────────────────────────────────────────────────

it('is not lazy by default', function () {
    expect(CustomWidget::make()->isLazy())->toBeFalse();
});

it('can be set to lazy', function () {
    expect(CustomWidget::make()->lazy()->isLazy())->toBeTrue();
});
