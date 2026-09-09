<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Core\Plugin\Hooks\WidgetConfiguringPayload;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Foundation\Enums\Hook;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/*
 * A dashboard, extendable by something that did not declare it.
 *
 * The ordering is what this file exists to pin. A key is derived from a widget's
 * index in the *unfiltered* list, so a hook that fired after the stamping would
 * hand back a widget with no key — unreachable by a poll tick — or one wearing a
 * key another widget already answers to. And a hook that fired after the
 * visibility filter would smuggle in a widget whose own `visible(false)` never
 * ran.
 */

class WchDashboard extends Component
{
    use WithWidgets;

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->heading('Declared')->stats([Stat::make('Users', '1')]),
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

/**
 * A dashboard with a header action, which is the one path that deliberately
 * clears the declaration memo.
 */
class WchActionDashboard extends Component
{
    use WithWidgets;

    public int $count = 0;

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->key('counter')
                ->headerActions([
                    Action::make('bump')->label('Bump')->action(fn () => $this->count++),
                ])
                ->stats([Stat::make('Count', 'count: '.$this->count)]),
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

function wchAdd(string $heading, bool $visible = true, ?string $for = null): void
{
    app(PluginManager::class)->hook(
        Hook::WidgetConfiguring,
        function (WidgetConfiguringPayload $payload) use ($heading, $visible): WidgetConfiguringPayload {
            $payload->widgets[] = StatsOverviewWidget::make()
                ->heading($heading)
                ->stats([Stat::make('Added', '2')])
                ->visible($visible);

            return $payload;
        },
        for: $for,
    );
}

it('renders a widget a hook added beside the declared ones', function () {
    wchAdd('Added by a plugin');

    Livewire::test(WchDashboard::class)
        ->assertSee('Declared')
        ->assertSee('Added by a plugin');
});

it('stamps an added widget with a key of its own', function () {
    // Not cosmetic: the key is what a poll tick names, so a widget without one
    // — or wearing a borrowed one — refreshes the wrong thing or nothing.
    wchAdd('Added by a plugin');

    $keys = array_map(
        fn ($widget): ?string => $widget->getKey(),
        (new WchDashboard)->getVisibleWidgets(),
    );

    expect($keys)->toBe(['w0', 'w1']);
});

it('honours an added widget that hides itself', function () {
    // Dispatched before the visibility filter, so `visible(false)` on a widget a
    // plugin added means the same thing as on one the dashboard declared.
    wchAdd('Added by a plugin', visible: false);

    Livewire::test(WchDashboard::class)
        ->assertSee('Declared')
        ->assertDontSee('Added by a plugin');
});

it('asks the hook once, however often the widgets are read', function () {
    // `getVisibleWidgets()` is called by the render and again by a poll tick. A
    // hook that ran per call would append the same widget twice on the second.
    wchAdd('Added by a plugin');

    $dashboard = new WchDashboard;

    expect($dashboard->getVisibleWidgets())->toHaveCount(2)
        ->and($dashboard->getVisibleWidgets())->toHaveCount(2);
});

it('leaves a host a scoped hook does not name alone', function () {
    // A plain `WithWidgets` component shows nothing registered, so it has no key
    // — and a callback written for one dashboard must not land on it.
    wchAdd('Added by a plugin', for: 'sales');

    Livewire::test(WchDashboard::class)->assertDontSee('Added by a plugin');
});

it('reaches a host named by its own class', function () {
    wchAdd('Added by a plugin', for: WchDashboard::class);

    Livewire::test(WchDashboard::class)->assertSee('Added by a plugin');
});

it('fires once more, and no more, when a header action rebuilds the declaration', function () {
    // `callWidgetAction()` clears the memo on purpose: the widget it just ran an
    // action on was built before the action, so re-rendering it would show the
    // state from before the click. The memo is what stops a render and a poll
    // tick both firing this hook, so the rebuild has to start from a fresh
    // `getWidgets()` — otherwise the appended widget would arrive twice.
    wchAdd('Added by a plugin');

    $html = Livewire::test(WchActionDashboard::class)
        ->call('callWidgetAction', 'counter', 'bump')
        ->html();

    expect(substr_count($html, 'Added by a plugin'))->toBe(1);
});
