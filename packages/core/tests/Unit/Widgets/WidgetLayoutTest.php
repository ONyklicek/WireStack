<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Exceptions\WidgetLayoutException;
use NyonCode\WireCore\Foundation\Preferences\Drivers\SessionPreferenceDriver;
use NyonCode\WireCore\Foundation\Preferences\PreferenceManager;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Support\WidgetLayout;

/**
 * What one user did to one dashboard, applied on the server.
 *
 * Deliberately the step before any dragging: order, size and placement are
 * decided in PHP and provable in PHP, so when a browser starts writing this
 * layout there is already a tested thing for it to write *to*. See
 * `architecture/plans/customisable-dashboards.md` step 3.
 */
class LayoutDashboard extends Component
{
    use WithWidgets;

    public bool $customisable = true;

    public bool $hideOrders = false;

    protected function widgetLayoutKey(): ?string
    {
        return $this->customisable ? 'sales' : null;
    }

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('revenue')->stats([Stat::make('Revenue', '1')]),
            StatsOverviewWidget::make()->key('orders')
                ->visible(fn (): bool => ! $this->hideOrders)
                ->stats([Stat::make('Orders', '2')]),
            StatsOverviewWidget::make()->key('churn')->stats([Stat::make('Churn', '3')]),
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

function layoutStore(array $placements): void
{
    PreferenceManager::swap($driver = new SessionPreferenceDriver);

    $driver->save('sales', null, WidgetLayout::of($placements)->toBag());
}

function layoutKeys(array $widgets): array
{
    return array_map(fn ($widget) => $widget->getKey(), $widgets);
}

beforeEach(fn () => PreferenceManager::swap(null));
afterEach(fn () => PreferenceManager::swap(null));

// ─── Nothing stored ──────────────────────────────────────────────────────────

it('leaves a dashboard nobody has touched exactly as it was declared', function () {
    $widgets = Livewire::test(LayoutDashboard::class)->instance()->getVisibleWidgets();

    expect(layoutKeys($widgets))->toBe(['revenue', 'orders', 'churn'])
        // Not merely the same order — the same spans, so the markup is what it
        // was before any of this existed.
        ->and($widgets[0]->getColumnSpan())->toBeNull()
        ->and($widgets[0]->getRowSpan())->toBeNull();
});

it('reads no store at all for a dashboard that never opted in', function () {
    // The opt-in is the whole guard: no key, no lookup, no layout.
    layoutStore([['key' => 'churn', 'w' => 1, 'h' => 1]]);

    $widgets = Livewire::test(LayoutDashboard::class, ['customisable' => false])
        ->instance()
        ->getVisibleWidgets();

    expect(layoutKeys($widgets))->toBe(['revenue', 'orders', 'churn']);
});

// ─── A stored layout ─────────────────────────────────────────────────────────

it('orders and sizes the widgets the way the user left them', function () {
    layoutStore([
        ['key' => 'churn', 'w' => 1, 'h' => 2],
        ['key' => 'revenue', 'w' => 2, 'h' => 1],
    ]);

    $widgets = Livewire::test(LayoutDashboard::class)->instance()->getVisibleWidgets();

    expect(layoutKeys($widgets))->toBe(['churn', 'revenue'])
        ->and($widgets[0]->getRowSpan())->toBe(2)
        ->and($widgets[1]->getColumnSpan())->toBe(2)
        // A declared widget the layout does not place is off the dashboard —
        // it is in the tray, waiting to be put back.
        ->and(layoutKeys($widgets))->not->toContain('orders');
});

it('draws the stored spans as grid classes', function () {
    layoutStore([['key' => 'revenue', 'w' => 2, 'h' => 2]]);

    $html = Livewire::test(LayoutDashboard::class)->html();

    expect($html)->toContain('row-span-2')
        ->and($html)->toContain('sm:col-span-2')
        // The row baseline only appears on a grid that has a tall tile.
        ->and($html)->toContain('auto-rows-');
});

it('leaves the row baseline off a grid where nothing is taller than a row', function () {
    // Fixing a row height for every dashboard would change how every existing
    // one looks — a card would stop being as tall as its contents.
    expect(Livewire::test(LayoutDashboard::class)->html())->not->toContain('auto-rows-');
});

it('empties a dashboard the user emptied, which is not the same as no layout', function () {
    layoutStore([]);

    expect(Livewire::test(LayoutDashboard::class)->instance()->getVisibleWidgets())->toBe([]);
});

// ─── A layout is a preference, never a grant ─────────────────────────────────

it('will not show a widget a policy hides, however the user arranged it', function () {
    layoutStore([
        ['key' => 'orders', 'w' => 1, 'h' => 1],
        ['key' => 'revenue', 'w' => 1, 'h' => 1],
    ]);

    $widgets = Livewire::test(LayoutDashboard::class, ['hideOrders' => true])
        ->instance()
        ->getVisibleWidgets();

    expect(layoutKeys($widgets))->toBe(['revenue']);
});

// ─── The bag arrives from a request ──────────────────────────────────────────

it('drops a key the declaration no longer has rather than conjuring a widget', function () {
    layoutStore([
        ['key' => 'revenue', 'w' => 1, 'h' => 1],
        ['key' => 'renamed-away', 'w' => 1, 'h' => 1],
    ]);

    expect(layoutKeys(Livewire::test(LayoutDashboard::class)->instance()->getVisibleWidgets()))
        ->toBe(['revenue']);
});

it('places a key listed twice once, at its first position', function () {
    $layout = WidgetLayout::of([
        ['key' => 'a', 'w' => 3, 'h' => 1],
        ['key' => 'b', 'w' => 1, 'h' => 1],
        ['key' => 'a', 'w' => 1, 'h' => 1],
    ]);

    expect(array_column($layout->toBag()['widgets'], 'key'))->toBe(['a', 'b'])
        // The first one, so the widget does not move for a reason nobody can see.
        ->and($layout->toBag()['widgets'][0]['w'])->toBe(3);
});

it('clamps a size that would render as no class at all', function () {
    // A stored w of 99 reaches getColumnSpanClass() and falls through its match.
    $layout = WidgetLayout::of([
        ['key' => 'a', 'w' => 99, 'h' => 99],
        ['key' => 'b', 'w' => 0, 'h' => -4],
    ]);

    expect($layout->toBag()['widgets'])->toBe([
        ['key' => 'a', 'w' => 4, 'h' => 6],
        ['key' => 'b', 'w' => 1, 'h' => 1],
    ]);
});

it('reads anything unrecognisable as no layout rather than as an error', function () {
    // The bag is shared with other surfaces and other versions of this one.
    expect(WidgetLayout::fromBag([])->isDeclared())->toBeTrue()
        ->and(WidgetLayout::fromBag(['widgets' => 'nonsense'])->isDeclared())->toBeTrue()
        ->and(WidgetLayout::fromBag(['columns' => ['hidden' => []]])->isDeclared())->toBeTrue()
        // An empty list, though, is a layout — the user took everything off.
        ->and(WidgetLayout::fromBag(['widgets' => []])->isDeclared())->toBeFalse();
});

it('ignores an entry that is not a placement', function () {
    $layout = WidgetLayout::of([
        'nonsense',
        ['w' => 2],
        ['key' => '', 'w' => 1],
        ['key' => 'a', 'w' => 1, 'h' => 1],
    ]);

    expect(array_column($layout->toBag()['widgets'], 'key'))->toBe(['a']);
});

// ─── One lookup per request ──────────────────────────────────────────────────

it('asks the store once, however often the widgets are read', function () {
    $driver = new class extends SessionPreferenceDriver
    {
        public int $loads = 0;

        public function load(string $surfaceKey, ?Authenticatable $user, ?string $view = null): array
        {
            $this->loads++;

            return ['widgets' => [['key' => 'revenue', 'w' => 1, 'h' => 1]]];
        }
    };

    PreferenceManager::swap($driver);

    $component = Livewire::test(LayoutDashboard::class)->instance();
    $component->getVisibleWidgets();
    $component->getVisibleWidgets();

    expect($driver->loads)->toBe(1);
});

// ─── A layout needs keys that survive the declaration changing ───────────────

it('refuses a customisable dashboard whose widgets have no key of their own', function () {
    // Derived keys are positions. A stored layout addresses widgets by key, so
    // the first widget inserted at the top would make every saved layout
    // describe different widgets than it did yesterday — the user's revenue card
    // is now their churn card, at the size they chose for revenue. The page
    // still renders, which is what makes it worth refusing rather than
    // documenting.
    $component = new class extends Component
    {
        use WithWidgets;

        protected function widgetLayoutKey(): ?string
        {
            return 'sales';
        }

        protected function getWidgets(): array
        {
            return [StatsOverviewWidget::make()->stats([Stat::make('Revenue', '1')])];
        }

        public function render()
        {
            return '<div></div>';
        }
    };

    expect(fn () => $component->getVisibleWidgets())
        ->toThrow(WidgetLayoutException::class, 'needs a key that survives the declaration changing');
});

it('says nothing about keys on a dashboard nobody rearranges', function () {
    // The positional key is right there — it is only a layout that makes it
    // wrong, so a dashboard without one keeps getting them for free.
    $component = new class extends Component
    {
        use WithWidgets;

        protected function getWidgets(): array
        {
            return [StatsOverviewWidget::make()->stats([Stat::make('Revenue', '1')])];
        }

        public function render()
        {
            return '<div></div>';
        }
    };

    expect($component->getVisibleWidgets()[0]->getKey())->toBe('w0');
});
