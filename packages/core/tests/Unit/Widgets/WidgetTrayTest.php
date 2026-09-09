<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/**
 * The tray: what a user can put on their dashboard but has not.
 *
 * "Not placed" and "available" are the same state on purpose — nothing records
 * that a widget was removed, it is simply no longer in the layout. One rule
 * doing two jobs, so the two cannot disagree, and taking a tile off puts it back
 * in the tray by construction rather than by a second mechanism.
 *
 * Step 5 of `architecture/plans/customisable-dashboards.md`.
 */
class TrayDashboard extends Component
{
    use WithWidgets;

    public bool $hideChurn = false;

    protected function widgetLayoutKey(): ?string
    {
        return 'sales';
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('revenue')->heading('Revenue')
                ->group('Money')->sizes([[2, 1], [4, 2]])
                ->stats([Stat::make('Total', '1')]),
            StatsOverviewWidget::make()->key('orders')->heading('Orders')
                ->group('Money')
                ->stats([Stat::make('Open', '2')]),
            StatsOverviewWidget::make()->key('churn')->heading('Churn')
                ->visible(fn (): bool => ! $this->hideChurn)
                ->stats([Stat::make('Rate', '3')]),
        ];
    }

    public function render()
    {
        return view('wire-core::widgets.widget-grid', $this->widgetGridData(2));
    }
}

function trayKeys(array $groups): array
{
    return collect($groups)->map(fn ($widgets) => array_map(fn ($w) => $w->getKey(), $widgets))->all();
}

function placedKeys(Component $component): array
{
    return array_map(fn ($w) => $w->getKey(), $component->getVisibleWidgets());
}

beforeEach(fn () => PreferenceManager::swap(new SessionPreferenceDriver));
afterEach(fn () => PreferenceManager::swap(null));

// ─── What the tray holds ─────────────────────────────────────────────────────

it('is empty while everything is still on the dashboard', function () {
    $component = Livewire::test(TrayDashboard::class)->call('startEditingWidgets');

    expect(trayKeys($component->instance()->getAvailableWidgets()))->toBe([]);
});

it('holds what the user took off', function () {
    $component = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'orders');

    expect(placedKeys($component->instance()))->toBe(['revenue', 'churn'])
        ->and(trayKeys($component->instance()->getAvailableWidgets()))->toBe(['Money' => ['orders']]);
});

it('groups by what each widget declares, ungrouped first', function () {
    $component = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'churn')
        ->call('removeWidget', 'revenue');

    // The ungrouped run comes under an empty key, and the named groups keep the
    // order the declaration mentioned them in.
    expect(trayKeys($component->instance()->getAvailableWidgets()))
        ->toBe(['Money' => ['revenue'], '' => ['churn']]);
});

it('will not offer a widget a policy hides', function () {
    // A tray offering something that vanishes when you add it is worse than a
    // tray that does not offer it.
    $component = Livewire::test(TrayDashboard::class, ['hideChurn' => true])
        ->call('startEditingWidgets')
        ->call('removeWidget', 'churn');

    expect(trayKeys($component->instance()->getAvailableWidgets()))->toBe([]);
});

it('holds nothing on a dashboard nobody may rearrange', function () {
    $component = new class extends Component
    {
        use WithWidgets;

        protected function getWidgets(): array
        {
            return [StatsOverviewWidget::make()->key('a')->stats([Stat::make('a', '1')])];
        }

        public function render()
        {
            return '<div></div>';
        }
    };

    expect($component->getAvailableWidgets())->toBe([]);
});

// ─── Putting one back ────────────────────────────────────────────────────────

it('puts a widget back where the drop reports it', function () {
    $component = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'revenue')
        ->call('placeWidget', 'revenue', 1);

    expect(placedKeys($component->instance()))->toBe(['orders', 'revenue', 'churn'])
        ->and(trayKeys($component->instance()->getAvailableWidgets()))->toBe([]);
});

it('adds it at the size it offers, not at one by one', function () {
    // A widget that only looks right at 2×1 should not arrive as a 1×1 the user
    // then has to fix.
    $component = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'revenue')
        ->call('placeWidget', 'revenue', 0);

    $placed = collect($component->get('widgetLayoutDraft'))->keyBy('key');

    expect($placed['revenue'])->toBe(['key' => 'revenue', 'w' => 2, 'h' => 1])
        // A widget declaring no sizes still arrives as one by one.
        ->and($placed['orders'])->toBe(['key' => 'orders', 'w' => 1, 'h' => 1]);
});

it('moves rather than duplicates a widget already on the dashboard', function () {
    // The same drop handler serves both directions, so this is the case that
    // would silently place a second copy if it did not check.
    $component = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('placeWidget', 'churn', 0);

    expect(placedKeys($component->instance()))->toBe(['churn', 'revenue', 'orders']);
});

it('ignores a key the dashboard does not declare', function () {
    $component = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('placeWidget', 'invented', 0);

    expect(placedKeys($component->instance()))->toBe(['revenue', 'orders', 'churn']);
});

it('changes nothing outside the mode', function () {
    $component = Livewire::test(TrayDashboard::class)
        ->call('removeWidget', 'revenue')
        ->call('placeWidget', 'revenue', 0);

    expect($component->get('widgetLayoutDraft'))->toBe([])
        ->and(placedKeys($component->instance()))->toBe(['revenue', 'orders', 'churn']);
});

// ─── Saving what the tray did ────────────────────────────────────────────────

it('keeps a removal once it is saved', function () {
    Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'orders')
        ->call('saveWidgetLayout');

    $fresh = Livewire::test(TrayDashboard::class);

    expect(placedKeys($fresh->instance()))->toBe(['revenue', 'churn'])
        // And the widget is offered again, rather than gone.
        ->and(trayKeys($fresh->call('startEditingWidgets')->instance()->getAvailableWidgets()))
        ->toBe(['Money' => ['orders']]);
});

// ─── The markup ──────────────────────────────────────────────────────────────

it('draws no tray until the mode is on', function () {
    expect(Livewire::test(TrayDashboard::class)->html())->not->toContain('widget-tray');
});

it('shares one drag group between the grid and the tray', function () {
    // What makes tray ↔ grid a single drag rather than two lists that need a
    // protocol between them.
    $html = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'orders')
        ->html();

    preg_match_all('/x-sort:group="([^"]+)"/', $html, $groups);

    expect($groups[1])->toHaveCount(2)
        ->and($groups[1][0])->toBe($groups[1][1])
        ->and($html)->toContain('x-sort="$wire.removeWidget($item)"')
        ->and($html)->toContain('data-testid="widget-tray-orders"')
        ->and($html)->toContain('data-testid="widget-remove-revenue"');
});

it('says the size a tray widget will arrive at', function () {
    $html = Livewire::test(TrayDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'revenue')
        ->html();

    expect($html)->toContain('2×1');
});

it('says so when the tray is empty rather than collapsing to nothing', function () {
    // A strip that disappears mid-session reads as a bug.
    $html = Livewire::test(TrayDashboard::class)->call('startEditingWidgets')->html();

    expect($html)->toContain('widget-tray')
        ->and($html)->toContain('Everything is on the dashboard.');
});
