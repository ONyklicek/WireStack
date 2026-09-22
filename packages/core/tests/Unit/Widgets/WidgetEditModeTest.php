<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

/**
 * A dashboard whose widgets declare the sizes they look right at.
 *
 * Separate from the one above because the two prove opposite halves of the same
 * rule: that one declares nothing and may be stepped through the whole grid,
 * this one declares pairs and may not leave them. A single fixture doing both
 * would have to be read twice to know which half a failing test was about.
 */
class SizedDashboard extends Component
{
    use WithWidgets;

    protected function widgetLayoutKey(): ?string
    {
        return 'sized';
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('revenue')->sizes([[2, 1]])
                ->stats([Stat::make('Revenue', '1')]),
            StatsOverviewWidget::make()->key('orders')->sizes([[3, 2], [1, 1]])
                ->stats([Stat::make('Orders', '2')]),
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

it('resizes a widget and snaps what the browser sent to this grid', function () {
    // Bounded by the dashboard's own columns, not by the widest grid there is.
    // A tile wider than its grid does not clip — CSS Grid *adds* the column and
    // squeezes every other tile on the dashboard into what is left, which is
    // what this used to allow: 3 and 4 on a two-column grid, both stored.
    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('resizeWidget', 'revenue', 3, 2)
        ->call('resizeWidget', 'orders', 99, -1);

    $draft = collect($component->get('widgetLayoutDraft'))->keyBy('key');

    expect($draft['revenue'])->toBe(['key' => 'revenue', 'w' => 2, 'h' => 2])
        ->and($draft['orders'])->toBe(['key' => 'orders', 'w' => 2, 'h' => 1]);
});

it('offers only the sizes a widget declared', function () {
    // `sizes()` has always been documented as narrowing what a widget is offered
    // at, and nothing read it: `getSizes()` had no caller anywhere, so a widget
    // declaring one size could be stepped to any of the grid's twenty-four.
    $component = Livewire::test(SizedDashboard::class)
        ->call('startEditingWidgets')
        // Not offered: 1×1 is not on the list, and the nearest one that is wins.
        ->call('resizeWidget', 'revenue', 1, 1);

    $draft = collect($component->get('widgetLayoutDraft'))->keyBy('key');

    expect($draft['revenue'])->toBe(['key' => 'revenue', 'w' => 2, 'h' => 1]);

    // And the stepper says so before anybody clicks: at the only height this
    // widget offers, both height buttons are dead ends.
    $html = $component->html();

    expect($html)->toMatch('/disabled[^>]*data-testid="widget-taller-revenue"/');
});

it('adds a widget from the tray at the size it offers, clamped to the grid', function () {
    // The tray's label and the tile have to agree, so both ask the same owner.
    $component = Livewire::test(SizedDashboard::class)
        ->call('startEditingWidgets')
        ->call('removeWidget', 'orders')
        ->call('placeWidget', 'orders', 0);

    $draft = collect($component->get('widgetLayoutDraft'))->keyBy('key');

    // Declared [[3, 2], [1, 1]] on a two-column dashboard: three columns is not
    // on offer here, so it arrives two wide and two tall.
    expect($draft['orders'])->toBe(['key' => 'orders', 'w' => 2, 'h' => 2]);
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

// ─── What the browser may not do ─────────────────────────────────────────────

/*
 * The draft and the mode are public properties because they have to survive the
 * round trips a drag makes — and a public property is writable from the browser
 * unless it says otherwise. Measured before they said so: a client set
 * `editingWidgets` true, wrote 500 invented placements into `widgetLayoutDraft`
 * and called Save. All 500 were stored — 15 kB in that user's preference row —
 * and the dashboard then rendered **empty**, because `apply()` drops every key
 * the declaration does not have and there was nothing else left.
 *
 * Two answers, because either alone leaves half of it standing: the properties
 * are locked, so the only way in is the methods that check a key against the
 * declaration and a size against the grid; and what is written is narrowed to
 * the declared keys anyway, which also cleans a bag left by an older one.
 */

it('refuses a draft the browser wrote itself', function () {
    expect(fn () => Livewire::test(EditModeDashboard::class)->set('widgetLayoutDraft', [
        ['key' => 'junk', 'w' => 2, 'h' => 2],
    ]))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('refuses a mode the browser opened itself', function () {
    // The mode is what the write guard in `updateDraft()` reads, so a client
    // that could set it true would be turning that guard off.
    expect(fn () => Livewire::test(EditModeDashboard::class)->set('editingWidgets', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('stores only what the dashboard declares, cleaning what an older one left', function () {
    // A widget renamed or removed by whoever owns the dashboard leaves its key
    // in every stored layout. It is unrenderable from that moment; saving is
    // where it stops being carried around.
    PreferenceManager::resolve()->save('sales', null, WidgetLayout::of([
        ['key' => 'revenue', 'w' => 1, 'h' => 1],
        ['key' => 'retired-widget', 'w' => 2, 'h' => 2],
        ['key' => 'orders', 'w' => 1, 'h' => 1],
    ])->toBag());

    $component = Livewire::test(EditModeDashboard::class)
        ->call('startEditingWidgets')
        ->call('saveWidgetLayout');

    $stored = PreferenceManager::resolve()->load('sales', null);

    expect(array_column($stored['widgets'], 'key'))->toBe(['revenue', 'orders'])
        ->and(editKeys($component->instance()->getVisibleWidgets()))->toBe(['revenue', 'orders']);
});

// ─── A full-width widget stays full width ────────────────────────────────────

/** A dashboard whose first row is a card across the whole grid. */
class FullWidthDashboard extends Component
{
    use WithWidgets;

    protected function widgetLayoutKey(): ?string
    {
        return 'full-width';
    }

    protected function getWidgetColumns(): int
    {
        return 2;
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('totals')->columnSpanFull()->stats([Stat::make('Revenue', '1')]),
            StatsOverviewWidget::make()->key('orders')->stats([Stat::make('Orders', '2')]),
        ];
    }

    public function render()
    {
        return view('wire-core::widgets.widget-grid', $this->widgetGridData(2));
    }
}

it('keeps a full-width widget full width when the editor opens', function () {
    // `columnSpanFull()` is the usual way to put a row of figures across the top
    // of a dashboard. The editor starts from what the declaration describes, and
    // it used to read 'full' as one column — so pressing Customise halved the
    // first row before anybody had touched it.
    $component = Livewire::test(FullWidthDashboard::class)->call('startEditingWidgets');

    $draft = collect($component->get('widgetLayoutDraft'))->keyBy('key');

    expect($draft['totals']['w'])->toBe(2)
        ->and($draft['orders']['w'])->toBe(1);
});
