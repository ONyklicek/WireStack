<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/**
 * A polling widget answers with itself, not with the dashboard
 * (architecture/plans/forms-and-surfaces-performance.md step 7).
 *
 * Livewire reads a bare `wire:poll` as `$refresh`
 * (`directive.expression ? directive.expression : "$refresh"`), and the widget
 * grid carries no island — island targeting needs `wire:island` on the origin or
 * `closestIsland(origin.el)` to find one. So one polling widget re-rendered the
 * whole host: every other widget, and any table sharing the component. Measured
 * on this repository, a 12-widget grid costs 6.5 ms and 57 219 B to render,
 * against 0.311 ms and 3 940 B for one widget on its own — 21× the time and 14.5×
 * the bytes, before anything else on the page is counted.
 *
 * Per-widget islands cannot fix it: an `@island` name is re-evaluated inside its
 * own compiled view file, which never receives the enclosing loop's variable, so
 * one island per widget does not compile at all (`IslandSemanticsTest` pins the
 * same rule for table rows). A partial is an ordinary attribute the server picks
 * at write time, which is exactly why it reaches where an island cannot.
 */
class WpDashboard extends Component
{
    use WithWidgets;

    public int $count = 3;

    public bool $hideFirst = false;

    public function mount(int $count = 3, bool $hideFirst = false): void
    {
        $this->count = $count;
        $this->hideFirst = $hideFirst;
    }

    protected function getWidgets(): array
    {
        return array_map(function (int $i) {
            $widget = StatsOverviewWidget::make()
                ->heading('Widget '.$i)
                ->stats([Stat::make('Users', '1 00'.$i)]);

            if ($i === 1 && $this->hideFirst) {
                $widget->visible(false);
            }

            // Only the second widget polls, so everything else on the page is a
            // witness for what a tick must not re-render.
            return $i === 2 ? $widget->pollingInterval('10s') : $widget;
        }, range(1, $this->count));
    }

    public function render()
    {
        return view('wire-core::widgets.widget-grid', [
            'widgets' => $this->getVisibleWidgets(),
            'columns' => 2,
        ]);
    }
}

it('points a polling widget at its own endpoint rather than at $refresh', function () {
    $html = Livewire::test(WpDashboard::class)->html();

    expect($html)->toContain('wire:poll.10s.visible="refreshWidget(\'w1\')"')
        ->and($html)->toContain('wire:partial="widget-w1"');
});

it('anchors only the widget that polls', function () {
    // A dashboard that polls nothing must emit exactly the markup it always did.
    $html = Livewire::test(WpDashboard::class)->html();

    expect(substr_count($html, 'wire:partial='))->toBe(1)
        ->and(substr_count($html, 'wire:poll'))->toBe(1);
});

it('answers a tick with that widget alone', function () {
    $component = Livewire::test(WpDashboard::class)->call('refreshWidget', 'w1');

    $effects = $component->effects;

    expect(array_keys($effects['wirePartials'] ?? []))->toBe(['widget-w1'])
        // The whole point: no full render alongside it.
        ->and($effects['html'] ?? null)->toBeNull();
});

it('sends the widget, and nothing that sits beside it', function () {
    $markup = Livewire::test(WpDashboard::class)
        ->call('refreshWidget', 'w1')
        ->effects['wirePartials']['widget-w1'];

    // StatsOverviewWidget renders its stats, not its heading, so the stat value
    // is what tells the three widgets apart in the markup.
    expect($markup)->toContain('1 002')
        ->and($markup)->not->toContain('1 001')
        ->and($markup)->not->toContain('1 003')
        // Single-rooted and carrying its own anchor, or the morph has nothing
        // to pair against on the next tick.
        ->and($markup)->toStartWith('<div wire:partial="widget-w1"');
});

it('falls back to a full render when the key names nothing', function () {
    // The coverage rule: a call that queued no region cannot answer partially,
    // because a partial response covering less than what changed is exactly the
    // staleness the mechanism exists to avoid.
    $effects = Livewire::test(WpDashboard::class)->call('refreshWidget', 'nope')->effects;

    expect($effects['wirePartials'] ?? null)->toBeNull()
        ->and($effects['html'] ?? null)->not->toBeNull();
});

it('keeps a widget key stable when an earlier widget is hidden', function () {
    // Keys come from the position in getWidgets(), not among the visible ones —
    // otherwise hiding the first widget would renumber the rest and a tick would
    // answer with the wrong one.
    $shown = Livewire::test(WpDashboard::class)->html();
    $hidden = Livewire::test(WpDashboard::class, ['hideFirst' => true])->html();

    expect($shown)->toContain('wire:partial="widget-w1"')
        ->and($hidden)->toContain('wire:partial="widget-w1"')
        ->and($hidden)->not->toContain('Widget 1');
});

it('lets a dashboard name its own widget keys', function () {
    expect(StatsOverviewWidget::make()->key('sales')->getKey())->toBe('sales')
        ->and(StatsOverviewWidget::make()->getKey())->toBeNull();
});
