<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\CustomWidget;
use NyonCode\WireCore\Widgets\ListItem;
use NyonCode\WireCore\Widgets\ListWidget;
use NyonCode\WireCore\Widgets\ProgressItem;
use NyonCode\WireCore\Widgets\ProgressWidget;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/**
 * A widget carries actions, and running one crosses a boundary it may not
 * import over.
 *
 * `Widgets` and `Actions` are sibling L2 modules (ADR 0025), so nothing in
 * `WithWidgets` knows what an `Action` is: the widget answers with the Foundation
 * contract and `RunsComponentActions` — resolved from the container, implemented
 * on the Actions side — runs it. `ModuleLayersTest` is what proves no import
 * sneaked in; these prove the route actually works.
 */
class WaDashboard extends Component
{
    use WithWidgets;

    public array $ran = [];

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->key('revenue')
                ->heading('Revenue')
                ->headerActions([
                    Action::make('recount')
                        ->label('Recount')
                        ->action(fn () => $this->ran[] = 'recount'),
                    Action::make('secret')
                        ->label('Secret')
                        ->hidden()
                        ->action(fn () => $this->ran[] = 'secret'),
                ])
                ->stats([Stat::make('Total', '42')]),

            StatsOverviewWidget::make()
                ->key('plain')
                ->stats([Stat::make('Other', '7')]),
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
 * A dashboard whose widget prints component state resolved at declaration time —
 * the shape that catches a re-render of the pre-action object.
 */
class WaCounterDashboard extends Component
{
    use WithWidgets;

    public int $count = 0;

    public bool $dismissed = false;

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->key('counter')
                ->visible(fn (): bool => ! $this->dismissed)
                ->headerActions([
                    Action::make('bump')->label('Bump')->action(fn () => $this->count++),
                    Action::make('dismiss')->label('Dismiss')->action(fn () => $this->dismissed = true),
                ])
                // An eager value, resolved when the widget is built. A closure
                // would re-resolve on its own, and "your action works if you
                // happened to pass a closure" is not a rule anyone should know.
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

// ─── The vocabulary is the canonical one ─────────────────────────────────────

it('carries actions through the shared display-component owner', function () {
    // Foundation\Concerns\HasActions, the same owner an infolist entry and a
    // schema section header use — not a widget-local list.
    $widget = CustomWidget::make()->headerActions([Action::make('export')]);

    expect($widget->hasActions())->toBeTrue()
        ->and($widget->getActions())->toHaveCount(1)
        ->and($widget->getFieldAction('export'))->not->toBeNull()
        ->and($widget->getFieldAction('nope'))->toBeNull()
        ->and(CustomWidget::make()->hasActions())->toBeFalse();
});

it('leaves a hidden action out of what renders', function () {
    $widget = CustomWidget::make()->headerActions([
        Action::make('shown'),
        Action::make('gone')->hidden(),
    ]);

    expect($widget->getActions())->toHaveCount(1)
        // Still resolvable by name, because the host has to be able to refuse it
        // rather than fail to find it.
        ->and($widget->getFieldAction('gone'))->not->toBeNull();
});

it('needs an anchor once it carries an action', function () {
    expect(CustomWidget::make()->key('w')->headerActions([Action::make('a')])->usesPartialAnchor())->toBeTrue();
});

it('does not draw a button with nothing to dispatch to', function () {
    // Without a key there is no expression, and the shared button falls back to
    // the infolist dispatch when handed none — a different method on a host that
    // has it for something else. A button that calls the wrong thing is worse
    // than one that is not there.
    $unwired = CustomWidget::make()->headerActions([Action::make('export')->label('Export')]);

    expect($unwired->hasActions())->toBeTrue()
        ->and($unwired->hasRenderableActions())->toBeFalse()
        ->and($unwired->usesPartialAnchor())->toBeFalse()
        ->and($unwired->toHtml())->not->toContain('widget-action-export');
});

it('applies the same rule to a url action, which is what keeps it one sentence', function () {
    // A link needs no host and could have been exempted, but asking an action
    // whether it carries a url means asking through ActionContract, whose two
    // methods are two on purpose. With a key — which WithWidgets always stamps —
    // a url action draws like any other.
    $keyless = CustomWidget::make()->headerActions([Action::make('docs')->label('Docs')->url('/docs')]);
    $keyed = CustomWidget::make()->key('w')->headerActions([Action::make('docs')->label('Docs')->url('/docs')]);

    expect($keyless->hasRenderableActions())->toBeFalse()
        ->and($keyed->hasRenderableActions())->toBeTrue()
        ->and($keyed->toHtml())->toContain('href="/docs"');
});

// ─── The click expression ────────────────────────────────────────────────────

it('addresses the action by widget key and action name', function () {
    $widget = CustomWidget::make()->key('sales');
    $action = Action::make('export');

    expect($widget->getActionExpression($action))->toBe("callWidgetAction('sales', 'export')");
});

it('has nothing to call without a key', function () {
    expect(CustomWidget::make()->getActionExpression(Action::make('export')))->toBeNull();
});

it('escapes what it splices into the expression', function () {
    // A declaration-time string today, but it is being spliced into a Livewire
    // expression — an action named from a match over user data would otherwise
    // be one quote away from changing what that expression says.
    $widget = CustomWidget::make()->key("it's");

    expect($widget->getActionExpression(Action::make("o'clock")))
        ->toBe("callWidgetAction('it\\'s', 'o\\'clock')");
});

// ─── Every widget surface draws them ─────────────────────────────────────────

it('draws header actions on every widget type', function (string $label, callable $make) {
    // The defect this pins is the one this repo has already shipped twice:
    // `StatsOverviewWidget::heading()` was API that drew nothing, because one of
    // the views did not read it. A widget whose headerActions() rendered nothing
    // would be the same bug, and asserting the getter would not catch it.
    //
    // Keyed, as `WithWidgets` keys every widget before anything renders: without
    // a key there is no expression to dispatch to, and an unwired button is
    // deliberately not drawn.
    $widget = $make()->key('w')->headerActions([Action::make('export')->label('Export')]);

    expect($widget->toHtml())
        ->toContain('data-testid="widget-action-export"')
        ->toContain('Export');
    // `TableWidget` is not here: it lives in wire-table now (it could never
    // render from core — see its class docblock), so its own package asserts the
    // same thing.
})->with([
    ['stats', fn () => StatsOverviewWidget::make()->stats([Stat::make('a', '1')])],
    ['chart', fn () => ChartWidget::make()],
    ['bar chart', fn () => BarChartWidget::make()->items([ChartItem::make('a')->value(1)])],
    ['progress', fn () => ProgressWidget::make()->items([ProgressItem::make('a')])],
    ['list', fn () => ListWidget::make()->items([ListItem::make('a')])],
    ['custom', fn () => CustomWidget::make()],
]);

it('renders a url action as a link rather than a dispatch', function () {
    $html = CustomWidget::make()
        ->key('w')
        ->headerActions([Action::make('docs')->label('Docs')->url('/docs')])
        ->toHtml();

    expect($html)->toContain('href="/docs"')
        ->and($html)->not->toContain('wire:click');
});

// ─── The round trip ──────────────────────────────────────────────────────────

it('wires the button to the host', function () {
    $html = Livewire::test(WaDashboard::class)->html();

    expect($html)->toContain('wire:click="callWidgetAction(&#039;revenue&#039;, &#039;recount&#039;)"')
        ->and($html)->toContain('data-testid="widget-action-recount"')
        // The hidden one is not on the page.
        ->and($html)->not->toContain('widget-action-secret');
});

it('runs the callback and answers with that widget alone', function () {
    $component = Livewire::test(WaDashboard::class)->call('callWidgetAction', 'revenue', 'recount');

    expect($component->get('ran'))->toBe(['recount'])
        ->and(array_keys($component->effects['wirePartials'] ?? []))->toBe(['widget-revenue'])
        ->and($component->effects['html'] ?? null)->toBeNull();
});

it('re-renders the widget the action changed, not the one it was clicked on', function () {
    // The object the action ran on is older than the action: a widget resolves
    // its data when getWidgets() builds it, so re-rendering that same object
    // shows the state from before the click. Found by a browser driver, with a
    // correct response and an empty console.
    $markup = Livewire::test(WaCounterDashboard::class)
        ->call('callWidgetAction', 'counter', 'bump')
        ->effects['wirePartials']['widget-counter'];

    expect($markup)->toContain('count: 1')
        ->and($markup)->not->toContain('count: 0');
});

it('falls back to a full render when an action removes its own widget', function () {
    // "Dismiss this card" is an ordinary action, and a partial can replace an
    // element but not delete one — so re-rendering the widget would leave one on
    // screen that the declaration no longer has.
    $component = Livewire::test(WaCounterDashboard::class)->call('callWidgetAction', 'counter', 'dismiss');

    expect($component->get('dismissed'))->toBeTrue()
        ->and($component->effects['wirePartials'] ?? null)->toBeNull()
        ->and($component->effects['html'] ?? null)->not->toBeNull()
        ->and($component->html())->not->toContain('widget-action-bump');
});

it('refuses to run an action the widget hides', function () {
    // Not clickable is not the same as not present: the name resolves, and the
    // host is what declines it.
    $component = Livewire::test(WaDashboard::class)->call('callWidgetAction', 'revenue', 'secret');

    expect($component->get('ran'))->toBe([])
        ->and($component->effects['wirePartials'] ?? null)->toBeNull();
});

it('falls back to a full render for an unknown key or name', function () {
    foreach ([['nope', 'recount'], ['revenue', 'nope'], ['plain', 'recount']] as [$key, $name]) {
        $effects = Livewire::test(WaDashboard::class)->call('callWidgetAction', $key, $name)->effects;

        expect($effects['wirePartials'] ?? null)->toBeNull()
            ->and($effects['html'] ?? null)->not->toBeNull();
    }
});
