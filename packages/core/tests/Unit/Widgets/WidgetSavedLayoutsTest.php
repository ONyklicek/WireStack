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
 * More than one arrangement of one dashboard, each under a name.
 *
 * The store has carried a `view` dimension since it was a table's — a saved view
 * is the same bag under a name — and the dashboard side passed it through and
 * never drove it: `widgetLayoutView()` was a seam with nothing on the other end,
 * so the feature existed in the driver and nowhere a user could reach it.
 *
 * Two decisions worth stating, because both could have gone the other way:
 *
 *  - **Applying is a copy, not a pointer.** A saved layout is written onto the
 *    current one, so nothing has to remember which name is in use and the answer
 *    survives a reload without a second piece of stored state to disagree with
 *    the first. The same thing `applyTableView()` does.
 *  - **The opt-in is its own.** `customisable()` is "you may rearrange this";
 *    `savedLayouts()` is "you may keep several arrangements", which is a
 *    switcher above the grid that a dashboard with one layout does not want.
 */
class SavedLayoutsDashboard extends Component
{
    use WithWidgets;

    public bool $saved = true;

    protected function widgetLayoutKey(): ?string
    {
        return 'sales';
    }

    public function hasSavedWidgetLayouts(): bool
    {
        return $this->saved;
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('revenue')->stats([Stat::make('Revenue', '1')]),
            StatsOverviewWidget::make()->key('orders')->stats([Stat::make('Orders', '2')]),
            StatsOverviewWidget::make()->key('churn')->stats([Stat::make('Churn', '3')]),
        ];
    }

    public function render()
    {
        return view('wire-core::widgets.widget-grid', $this->widgetGridData(2));
    }
}

function savedKeys(array $widgets): array
{
    return array_map(fn ($widget) => $widget->getKey(), $widgets);
}

beforeEach(fn () => PreferenceManager::swap(new SessionPreferenceDriver));
afterEach(fn () => PreferenceManager::swap(null));

// ─── Saving one ──────────────────────────────────────────────────────────────

it('keeps the arrangement on screen under a name', function () {
    $component = Livewire::test(SavedLayoutsDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        // While editing, what is saved is the draft: the arrangement the user is
        // looking at, not the one they last agreed to keep.
        ->call('saveWidgetLayoutAs', 'Morning');

    expect($component->instance()->getWidgetLayoutNames())->toBe(['Morning']);

    $stored = PreferenceManager::resolve()->load('sales', null, 'Morning');

    expect(array_column($stored['widgets'], 'key'))->toBe(['churn', 'revenue', 'orders']);
});

it('captures what the grid shows on a dashboard nobody has arranged', function () {
    // Nothing is stored, so the layout is the declaration — and a name over
    // "the declaration" would restore nothing. It is captured as placements
    // instead, which is what the editor starts from too.
    Livewire::test(SavedLayoutsDashboard::class)->call('saveWidgetLayoutAs', 'Default');

    $stored = PreferenceManager::resolve()->load('sales', null, 'Default');

    expect(array_column($stored['widgets'], 'key'))->toBe(['revenue', 'orders', 'churn']);
});

it('refuses a name that is not one', function () {
    // The empty name is the unnamed current layout. Saving to it here would
    // overwrite the live layout with itself and put a blank row in the switcher.
    $component = Livewire::test(SavedLayoutsDashboard::class)
        ->call('saveWidgetLayoutAs', '   ');

    expect($component->instance()->getWidgetLayoutNames())->toBe([]);
});

// ─── Putting one back ────────────────────────────────────────────────────────

it('copies a saved arrangement onto the dashboard, for good', function () {
    $component = Livewire::test(SavedLayoutsDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        ->call('saveWidgetLayoutAs', 'Morning')
        ->call('cancelEditingWidgets')
        ->call('applyWidgetLayout', 'Morning');

    expect(savedKeys($component->instance()->getVisibleWidgets()))->toBe(['churn', 'revenue', 'orders'])
        // A copy, not a pointer: a fresh component reads the current layout and
        // finds the same thing, with nothing remembering a name.
        ->and(savedKeys(Livewire::test(SavedLayoutsDashboard::class)->instance()->getVisibleWidgets()))
        ->toBe(['churn', 'revenue', 'orders']);
});

it('leaves the dashboard alone for a name with nothing under it', function () {
    // Deleted in another tab, or never there. Reverting to the declaration
    // because of it would be the opposite of what the click asked for.
    $component = Livewire::test(SavedLayoutsDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'orders', 0)
        ->call('saveWidgetLayout')
        ->call('applyWidgetLayout', 'Nope');

    expect(savedKeys($component->instance()->getVisibleWidgets()))->toBe(['orders', 'revenue', 'churn']);
});

it('closes the editor when a saved layout arrives', function () {
    // The draft was an arrangement of the layout that has just been replaced.
    $component = Livewire::test(SavedLayoutsDashboard::class)
        ->call('saveWidgetLayoutAs', 'Default')
        ->call('startEditingWidgets')
        ->call('applyWidgetLayout', 'Default');

    expect($component->get('editingWidgets'))->toBeFalse()
        ->and($component->get('widgetLayoutDraft'))->toBe([]);
});

// ─── Forgetting one ──────────────────────────────────────────────────────────

it('forgets a saved layout without touching the one on screen', function () {
    $component = Livewire::test(SavedLayoutsDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        ->call('saveWidgetLayout')
        ->call('saveWidgetLayoutAs', 'Morning')
        ->call('deleteWidgetLayout', 'Morning');

    expect($component->instance()->getWidgetLayoutNames())->toBe([])
        ->and(savedKeys($component->instance()->getVisibleWidgets()))->toBe(['churn', 'revenue', 'orders']);
});

// ─── The opt-in ──────────────────────────────────────────────────────────────

it('does nothing at all on a dashboard that did not ask for names', function () {
    // Every one of these is a public Livewire method, so the browser can call
    // them whether or not the switcher was ever drawn.
    $component = Livewire::test(SavedLayoutsDashboard::class, ['saved' => false])
        ->call('saveWidgetLayoutAs', 'Morning')
        ->call('applyWidgetLayout', 'Morning')
        ->call('deleteWidgetLayout', 'Morning');

    expect($component->instance()->getWidgetLayoutNames())->toBe([])
        ->and(PreferenceManager::resolve()->views('sales', null))->toBe([]);
});

// ─── The markup ──────────────────────────────────────────────────────────────

/**
 * The controls, rendered the way the page that ships them does.
 *
 * The partial is included by `wire-panels`' dashboard page rather than by the
 * grid, so a grid-rendering fixture never draws it — asking the view directly
 * with the host's own payload is the same call that page makes.
 */
function savedLayoutControls(SavedLayoutsDashboard $host): string
{
    return view('wire-core::widgets.partials.widget-layout-controls', $host->widgetGridData(2))->render();
}

it('draws the switcher only once something is saved', function () {
    $component = Livewire::test(SavedLayoutsDashboard::class);

    // The "save as" button from the start — it is what fills the list — and no
    // select, which empty would read as a list that failed to load.
    expect(savedLayoutControls($component->instance()))
        ->toContain('data-testid="widget-layout-save-as"')
        ->not->toContain('data-testid="widget-layout-view"');

    $component->call('saveWidgetLayoutAs', 'Morning');

    expect(savedLayoutControls($component->instance()))
        ->toContain('data-testid="widget-layout-view"')
        ->toContain('>Morning</option>')
        ->toContain('data-testid="widget-layout-delete"');
});

it('draws no switcher on a dashboard that did not ask for names', function () {
    $component = Livewire::test(SavedLayoutsDashboard::class, ['saved' => false])
        ->call('saveWidgetLayoutAs', 'Morning');

    $html = savedLayoutControls($component->instance());

    expect($html)->not->toContain('widget-layout-save-as')
        ->not->toContain('widget-layout-view')
        // …while the rearranging chrome it did ask for is still there.
        ->toContain('widget-layout-edit');
});
