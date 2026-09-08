<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;

/**
 * A filter is a widget capability, and it is resolved on the server.
 *
 * It used to be neither. `filter()` lived on `ChartWidget` alone, so "last 7
 * days / this month" was a chart feature — and it was wired to an Alpine
 * `updateChart()` that assigned `this.labels` and `this.datasets` back onto the
 * chart it was built with, so changing the selection redrew the identical chart
 * and the dataset closure never ran with anything but its default.
 *
 * Both halves are fixed here: the vocabulary moved to `HasWidgetFilter` on the
 * base, and the selection round-trips to the host, which is where the closure
 * is.
 */
class WfDashboard extends Component
{
    use WithWidgets;

    protected function getWidgets(): array
    {
        return [
            ListWidget::make()
                ->heading('Recent orders')
                ->filter(['week' => 'This week', 'month' => 'This month'])
                ->items(fn (?string $filter) => [ListItem::make('range: '.$filter)]),
            ListWidget::make()
                ->heading('Unfiltered')
                ->items([ListItem::make('always')]),
        ];
    }

    public function render()
    {
        return view('wire-core::widgets.widget-grid', [
            'widgets' => $this->getVisibleWidgets(),
            'columns' => 2,
        ]);
    }
}

// ─── The vocabulary is every widget's ────────────────────────────────────────

it('is available on any widget, not only on a chart', function () {
    $list = ListWidget::make()->filter(['week' => 'This week', 'month' => 'This month']);

    expect($list->hasFilter())->toBeTrue()
        ->and($list->getFilterOptions())->toBe(['week' => 'This week', 'month' => 'This month'])
        // The first option is the selection until somebody chooses.
        ->and($list->getActiveFilter())->toBe('week')
        ->and(ListWidget::make()->hasFilter())->toBeFalse()
        ->and(ListWidget::make()->getActiveFilter())->toBeNull();
});

it('takes an explicit default', function () {
    expect(ChartWidget::make()->filter(['week' => 'W', 'month' => 'M'], 'month')->getActiveFilter())
        ->toBe('month');
});

it('answers only for the options it enumerated', function () {
    // The key and the value both travel in the request, so an edited payload
    // must not put an arbitrary string in front of somebody's match.
    $widget = ListWidget::make()->filter(['week' => 'W', 'month' => 'M']);

    expect($widget->applyFilter('month')->getActiveFilter())->toBe('month')
        ->and($widget->applyFilter('../../etc/passwd')->getActiveFilter())->toBe('month')
        ->and($widget->applyFilter(null)->getActiveFilter())->toBe('month');
});

it('has nothing to call without a key to be addressed by', function () {
    expect(ListWidget::make()->filter(['week' => 'W'])->getFilterExpression())->toBeNull()
        ->and(ListWidget::make()->filter(['week' => 'W'])->key('sales')->getFilterExpression())
        ->toBe("filterWidget('sales', \$event.target.value)");
});

// ─── The round trip ──────────────────────────────────────────────────────────

it('draws a select wired to the host', function () {
    $html = Livewire::test(WfDashboard::class)->html();

    // Escaped by Blade, decoded by the HTML parser before Livewire ever sees it —
    // the same shape `callInfolistAction(&#039;edit&#039;)` has shipped in for as
    // long as infolist actions have existed.
    expect($html)->toContain('wire:change="filterWidget(&#039;w0&#039;, $event.target.value)"')
        ->and($html)->toContain('<option value="week" selected>')
        ->and($html)->toContain('range: week');
});

it('re-resolves the closure with the chosen key', function () {
    $markup = Livewire::test(WfDashboard::class)
        ->call('filterWidget', 'w0', 'month')
        ->effects['wirePartials']['widget-w0'];

    expect($markup)->toContain('range: month')
        ->and($markup)->not->toContain('range: week')
        ->and($markup)->toStartWith('<div wire:partial="widget-w0"');
});

it('answers with that widget alone', function () {
    $effects = Livewire::test(WfDashboard::class)->call('filterWidget', 'w0', 'month')->effects;

    expect(array_keys($effects['wirePartials'] ?? []))->toBe(['widget-w0'])
        ->and($effects['html'] ?? null)->toBeNull();
});

it('keeps the selection across the next render', function () {
    $html = Livewire::test(WfDashboard::class)
        ->call('filterWidget', 'w0', 'month')
        ->call('$refresh')
        ->html();

    expect($html)->toContain('range: month')
        ->and($html)->toContain('<option value="month" selected>');
});

it('falls back to a full render for a widget that offers no filter', function () {
    // The coverage rule: a call that queued no region cannot answer partially.
    $effects = Livewire::test(WfDashboard::class)->call('filterWidget', 'w1', 'month')->effects;

    expect($effects['wirePartials'] ?? null)->toBeNull()
        ->and($effects['html'] ?? null)->not->toBeNull();
});
