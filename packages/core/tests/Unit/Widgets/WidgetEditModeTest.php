<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Support\WidgetLayout;

/**
 * Rearranging a dashboard: an explicit mode, a draft, and a server that decides.
 *
 * The mode is the point. A live drag persists on every drop, so a stray grab
 * overwrites a layout somebody was happy with and there is nothing to undo it —
 * the draft below is what gives Cancel something to throw away.
 *
 * Step 4 of `architecture/plans/customisable-dashboards.md`. The drag itself is
 * Livewire's `x-sort`, so the browser half is verified by a CDP driver; what is
 * here is everything the server owns.
 */
class EditModeDashboard extends Component
{
    use WithWidgets;

    public bool $customisable = true;

    protected function widgetLayoutKey(): ?string
    {
        return $this->customisable ? 'sales' : null;
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

function editKeys(array $widgets): array
{
    return array_map(fn ($widget) => $widget->getKey(), $widgets);
}

beforeEach(fn () => PreferenceManager::swap(new SessionPreferenceDriver));
afterEach(fn () => PreferenceManager::swap(null));

// ─── Entering the mode ───────────────────────────────────────────────────────

it('starts from the declaration when nobody has arranged anything yet', function () {
    // So the first drop does not have to invent a starting order.
    $component = Livewire::test(EditModeDashboard::class)->call('startEditingWidgets');

    expect($component->get('editingWidgets'))->toBeTrue()
        ->and(array_column($component->get('widgetLayoutDraft'), 'key'))
        ->toBe(['revenue', 'orders', 'churn']);
});

it('starts from the stored layout when there is one', function () {
    PreferenceManager::resolve()->save('sales', null, WidgetLayout::of([
        ['key' => 'churn', 'w' => 2, 'h' => 1],
        ['key' => 'revenue', 'w' => 1, 'h' => 1],
    ])->toBag());

    $component = Livewire::test(EditModeDashboard::class)->call('startEditingWidgets');

    expect(array_column($component->get('widgetLayoutDraft'), 'key'))->toBe(['churn', 'revenue']);
});

it('refuses to open on a dashboard nobody may rearrange', function () {
    $component = Livewire::test(EditModeDashboard::class, ['customisable' => false])
        ->call('startEditingWidgets');

    expect($component->get('editingWidgets'))->toBeFalse()
        ->and($component->get('widgetLayoutDraft'))->toBe([]);
});

// ─── Moving and resizing ─────────────────────────────────────────────────────

it('moves a widget to where the drop reports it', function () {
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0);

    expect(array_column($component->get('widgetLayoutDraft'), 'key'))
        ->toBe(['churn', 'revenue', 'orders']);
});

it('counts the position after the widget is taken out, which is what a drag reports', function () {
    // Moving downwards is the reading that separates the two: taking the item
    // out shifts everything after it up by one, so a position counted before
    // the removal lands one place too far.
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'revenue', 2);

    expect(array_column($component->get('widgetLayoutDraft'), 'key'))
        ->toBe(['orders', 'churn', 'revenue']);
});

it('resizes a widget and clamps what the browser sent', function () {
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('resizeWidget', 'revenue', 3, 2)
        ->call('resizeWidget', 'orders', 99, -1);

    $draft = collect($component->get('widgetLayoutDraft'))->keyBy('key');

    expect($draft['revenue'])->toBe(['key' => 'revenue', 'w' => 3, 'h' => 2])
        ->and($draft['orders'])->toBe(['key' => 'orders', 'w' => 4, 'h' => 1]);
});

it('ignores a move or resize aimed at a widget the layout does not have', function () {
    // The browser sent it; a drag of something that is not here is not a request
    // to add it.
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'nope', 0)
        ->call('resizeWidget', 'nope', 2, 2);

    expect(array_column($component->get('widgetLayoutDraft'), 'key'))
        ->toBe(['revenue', 'orders', 'churn']);
});

it('changes nothing at all outside the mode', function () {
    // Every one of these is a public Livewire method, so the browser can call it
    // whenever it likes — including on a dashboard nobody opened the editor on.
    $component = Livewire::test(EditModeDashboard::class)
        ->call('moveWidget', 'churn', 0)
        ->call('resizeWidget', 'revenue', 4, 4);

    expect($component->get('widgetLayoutDraft'))->toBe([])
        ->and(editKeys($component->instance()->getVisibleWidgets()))
        ->toBe(['revenue', 'orders', 'churn']);
});

// ─── The draft is what the grid shows ────────────────────────────────────────

it('renders the draft while editing, not what was last saved', function () {
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0);

    expect(editKeys($component->instance()->getVisibleWidgets()))
        ->toBe(['churn', 'revenue', 'orders']);
});

// ─── Save, cancel, reset ─────────────────────────────────────────────────────

it('keeps the arrangement on save, and leaves the mode', function () {
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        ->call('resizeWidget', 'churn', 2, 2)
        ->call('saveWidgetLayout');

    expect($component->get('editingWidgets'))->toBeFalse()
        ->and($component->get('widgetLayoutDraft'))->toBe([])
        // Read back through a fresh component: this is what the user sees next
        // time, not what this one happened to be holding.
        ->and(editKeys(Livewire::test(EditModeDashboard::class)->instance()->getVisibleWidgets()))
        ->toBe(['churn', 'revenue', 'orders']);
});

it('throws the draft away on cancel', function () {
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        ->call('cancelEditingWidgets');

    expect($component->get('editingWidgets'))->toBeFalse()
        ->and(editKeys($component->instance()->getVisibleWidgets()))
        ->toBe(['revenue', 'orders', 'churn']);
});

it('writes nothing until save says so', function () {
    Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        ->call('cancelEditingWidgets');

    expect(PreferenceManager::resolve()->load('sales', null))->toBe([]);
});

it('forgets the layout on reset, which is not the same as emptying it', function () {
    Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('moveWidget', 'churn', 0)
        ->call('saveWidgetLayout');

    $component = Livewire::test(EditModeDashboard::class)->call('resetWidgetLayout');

    expect($component->get('editingWidgets'))->toBeFalse()
        ->and(PreferenceManager::resolve()->load('sales', null))->toBe([])
        ->and(editKeys($component->instance()->getVisibleWidgets()))
        ->toBe(['revenue', 'orders', 'churn']);
});

it('saves nothing on a dashboard nobody may rearrange', function () {
    Livewire::test(EditModeDashboard::class, ['customisable' => false])
        ->call('saveWidgetLayout')
        ->call('resetWidgetLayout');

    expect(PreferenceManager::resolve()->load('sales', null))->toBe([]);
});

// ─── The markup ──────────────────────────────────────────────────────────────

it('draws no handles, no steppers and no sort directive until the mode is on', function () {
    // A dashboard somebody is reading should be a dashboard, not a dashboard
    // wearing controls.
    $html = Livewire::test(EditModeDashboard::class)->html();

    expect($html)->not->toContain('x-sort')
        ->and($html)->not->toContain('widget-drag-')
        ->and($html)->not->toContain('widget-wider-');
});

it('wires the drag and the steppers to the host in the mode', function () {
    $component = Livewire::test(EditModeDashboard::class)->call('startEditingWidgets');
    $html = $component->html();

    // Asked of the widget rather than spelled here: the expression is built in
    // PHP, the same as the filter's and an action's — and escaped by Blade on
    // the way into the attribute, which the HTML parser undoes before Livewire
    // ever sees it.
    $wider = e($component->instance()->getVisibleWidgets()[0]->getResizeExpression(2, 1));

    // `placeWidget`, not `moveWidget`: a drop on the grid can be a tile moving
    // within it or one arriving from the tray, and the browser cannot tell them
    // apart.
    expect($html)->toContain('x-sort="$wire.placeWidget($item, $position)"')
        // A JS literal, not the bare key: the plugin evaluates what this holds,
        // so `x-sort:item="revenue"` reads as an identifier and throws — which a
        // browser driver found and this assertion had happily allowed.
        ->and($html)->toContain('x-sort:item="\'revenue\'"')
        ->and($html)->toContain('data-testid="widget-drag-revenue"')
        ->and($html)->toContain($wider)
        // Keyed cells are what let the server place the tile without the drop
        // being dragged back first.
        ->and($html)->toContain('wire:key="widget-cell-revenue"');
});

it('will not offer a size the grid does not have', function () {
    $html = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('resizeWidget', 'revenue', 4, 1)
        ->html();

    // At full width the "wider" stepper is disabled rather than silently
    // clamping on the way back.
    expect($html)->toMatch('/data-testid="widget-wider-revenue"[^>]*/')
        ->and($html)->toContain('disabled');
});
