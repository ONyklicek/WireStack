<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/**
 * A deferred widget draws a placeholder and fetches itself afterwards.
 *
 * `Widget::lazy()` shipped once before and was removed in 2.0 because no view
 * read the flag. The upgrade note for that removal said per-widget deferral was
 * not available at all — because it would need an `@island` per widget, and an
 * island inside a `@foreach` does not compile (its name is re-evaluated in its
 * own compiled file, which never sees the loop variable).
 *
 * That was true of islands and only of islands. A partial is chosen by the
 * server and anchored with a plain attribute, which is why polling already
 * answers one widget's tick with one widget. This is the same mechanism with a
 * different trigger, and it is the route the plan named
 * (architecture/plans/forms-and-surfaces-performance.md step 4) as the one that
 * would work.
 */
class WlDashboard extends Component
{
    use WithWidgets;

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->heading('Lifetime revenue')
                ->lazy()
                ->stats([Stat::make('Revenue', '1 234 567')]),
            StatsOverviewWidget::make()
                ->heading('Eager')
                ->stats([Stat::make('Users', '42')]),
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

it('draws a placeholder and asks the host for the real thing', function () {
    $html = Livewire::test(WlDashboard::class)->html();

    expect($html)->toContain('wire:init="loadWidget(\'w0\')"')
        ->and($html)->toContain('wire:partial="widget-w0"')
        ->and($html)->toContain('wire-widget-placeholder')
        // The figure has not been computed yet, and must not be on the page.
        ->and($html)->not->toContain('1 234 567')
        // The eager widget beside it is untouched.
        ->and($html)->toContain('42');
});

it('keeps the heading it already knows, and announces the wait', function () {
    $html = Livewire::test(WlDashboard::class)->html();

    expect($html)->toContain('Lifetime revenue')
        ->and($html)->toContain('aria-busy="true"')
        ->and($html)->toContain('Loading…');
});

it('anchors and defers only the widget that asked for it', function () {
    $html = Livewire::test(WlDashboard::class)->html();

    expect(substr_count($html, 'wire:partial='))->toBe(1)
        ->and(substr_count($html, 'wire:init='))->toBe(1);
});

it('answers the load with that widget alone', function () {
    $effects = Livewire::test(WlDashboard::class)->call('loadWidget', 'w0')->effects;

    expect(array_keys($effects['wirePartials'] ?? []))->toBe(['widget-w0'])
        ->and($effects['html'] ?? null)->toBeNull()
        ->and($effects['wirePartials']['widget-w0'])->toContain('1 234 567')
        ->and($effects['wirePartials']['widget-w0'])->not->toContain('wire-widget-placeholder');
});

it('stays loaded, so a later render does not fall back to the skeleton', function () {
    $component = Livewire::test(WlDashboard::class)->call('loadWidget', 'w0');

    expect($component->get('loadedWidgets'))->toBe(['w0']);

    $html = $component->call('$refresh')->html();

    expect($html)->toContain('1 234 567')
        ->and($html)->not->toContain('wire-widget-placeholder')
        // And it stops asking to be loaded.
        ->and($html)->not->toContain('wire:init=');
});

it('records a key once however often the load fires', function () {
    $component = Livewire::test(WlDashboard::class)
        ->call('loadWidget', 'w0')
        ->call('loadWidget', 'w0');

    expect($component->get('loadedWidgets'))->toBe(['w0']);
});

it('falls back to a full render when the key names nothing', function () {
    $effects = Livewire::test(WlDashboard::class)->call('loadWidget', 'nope')->effects;

    expect($effects['wirePartials'] ?? null)->toBeNull()
        ->and($effects['html'] ?? null)->not->toBeNull();
});
