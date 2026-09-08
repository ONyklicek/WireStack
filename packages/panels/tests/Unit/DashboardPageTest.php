<?php

declare(strict_types=1);

use Livewire\Livewire;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Widget;
use NyonCode\WirePanels\Resources\Pages\DashboardPage;

/*
 * A page that renders one dashboard.
 *
 * The same shape as ListPageTest, and for the same reason: both ways of
 * declaring the page are first class, and the assertions worth having are the
 * two that are neither — a page with no dashboard, and a page pointed at
 * something that is not one. Both would otherwise render an empty grid, and
 * empty reads as "no widgets" rather than as a mistake.
 */

final class DpSalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()->heading('Revenue')->stats([Stat::make('Total', '1.2M')]),
            StatsOverviewWidget::make()->heading('Orders')->stats([Stat::make('Open', '12')]),
        ];
    }

    public function columns(): int
    {
        return 3;
    }
}

class DpSalesPage extends DashboardPage
{
    protected static ?string $dashboard = DpSalesDashboard::class;
}

/** A dashboard a user may rearrange, and the page that carries its key. */
final class DpCustomisableDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [StatsOverviewWidget::make()->key('revenue')->stats([Stat::make('Total', '1')])];
    }

    public function customisable(): bool
    {
        return true;
    }
}

class DpCustomisablePage extends DashboardPage
{
    protected static ?string $dashboard = DpCustomisableDashboard::class;

    public function layoutKey(): ?string
    {
        return $this->widgetLayoutKey();
    }
}

/** The standalone path: no dashboard, widgets declared here. */
class DpStandalonePage extends DashboardPage
{
    protected function getWidgets(): array
    {
        return [StatsOverviewWidget::make()->heading('Local')->stats([Stat::make('Rows', '3')])];
    }
}

class DpNoDashboardPage extends DashboardPage {}

class DpNotADashboard {}

class DpWrongTypePage extends DashboardPage
{
    /** @phpstan-ignore-next-line deliberately wrong, which is what the test is about */
    protected static ?string $dashboard = DpNotADashboard::class;
}

it('renders the widgets the dashboard declared', function () {
    Livewire::test(DpSalesPage::class)
        ->assertOk()
        ->assertSee('Revenue')
        ->assertSee('Total')
        ->assertSee('1.2M')
        ->assertSee('Orders');
});

it('titles the page with the dashboard label', function () {
    // Derived, not repeated: the label is the dashboard's word for itself.
    Livewire::test(DpSalesPage::class)->assertSee('Dp Sales');
});

it('takes the column count from the dashboard', function () {
    $page = new DpSalesPage;
    $columns = (fn (): int => $this->getWidgetColumns())->call($page);

    expect($columns)->toBe(3);
});

it('renders a page that declares its own widgets and names no dashboard', function () {
    // The standalone path, first class exactly as it is for the resource pages.
    Livewire::test(DpStandalonePage::class)
        ->assertOk()
        ->assertSee('Local')
        ->assertSee('Rows');

    $page = new DpStandalonePage;

    expect((fn (): int => $this->getWidgetColumns())->call($page))->toBe(2)
        ->and(DpStandalonePage::dashboardClass())->toBeNull();
});

it('refuses a page that names no dashboard and declares no widgets', function () {
    // An empty grid would read as "no widgets" rather than as a mistake.
    expect(test()->refusalMessage(DpNoDashboardPage::class))
        ->toContain('has nothing to render')
        ->toContain(Dashboard::class);
});

it('refuses a page pointed at something that is not a dashboard', function () {
    // Asserting the *contract it names*, not just the class: the first version
    // of this test passed for the wrong reason — the page read a label off the
    // unvalidated class first, so what it actually caught was PHP's "call to
    // undefined method", whose message also contains the class name. The
    // refusal below was unreachable, and only the coverage floor said so.
    expect(test()->refusalMessage(DpWrongTypePage::class))
        ->toContain(DpNotADashboard::class)
        ->toContain('does not implement')
        ->toContain(Dashboard::class);
});

it('reports which dashboard it belongs to', function () {
    expect(DpSalesPage::dashboardClass())->toBe(DpSalesDashboard::class);
});

it('lets a page override the title without touching the dashboard', function () {
    $page = new DpSalesPage;
    (fn () => $this->title = 'This quarter')->call($page);

    expect($page->getTitle())->toBe('This quarter');
});

it('keeps every widget the dashboard gave it', function () {
    $widgets = (new DpSalesDashboard)->getWidgets();

    expect($widgets)->toHaveCount(2)
        ->and($widgets[0])->toBeInstanceOf(Widget::class);
});

// ─── The layout key ─────────────────────────────────────────────────────────

it('carries the declared dashboard\'s key when that dashboard opts in', function () {
    // The same bridge `hookKey()` builds: the widgets are declared inside a
    // dashboard the application may not own, so the key it registered under is
    // the handle anything outside has on them.
    expect(Livewire::test(DpCustomisablePage::class)->instance()->layoutKey())
        ->toBe(DpCustomisableDashboard::key());
});

it('carries no key for a dashboard that never opted in', function () {
    $page = new class extends DpSalesPage
    {
        public function layoutKey(): ?string
        {
            return $this->widgetLayoutKey();
        }
    };

    expect($page->layoutKey())->toBeNull();
});

it('carries no key on the standalone path until somebody names one', function () {
    // Deriving one from the class name would tie a user's saved layout to a
    // class they do not control: rename the page and every layout is orphaned,
    // with no error to say so.
    $page = new class extends DpStandalonePage
    {
        public function layoutKey(): ?string
        {
            return $this->widgetLayoutKey();
        }
    };

    expect($page->layoutKey())->toBeNull();
});

it('lets a standalone page name its own layout key', function () {
    $page = new class extends DpStandalonePage
    {
        protected static ?string $layoutKey = 'operations';

        public function layoutKey(): ?string
        {
            return $this->widgetLayoutKey();
        }
    };

    expect($page->layoutKey())->toBe('operations');
});

// ─── The ready-made controls ────────────────────────────────────────────────

it('draws the rearrange controls on a customisable dashboard', function () {
    // The methods are wire-core's and the chrome stays the caller's to write —
    // this is the common case made free, not the only way to have it.
    $html = Livewire::test(DpCustomisablePage::class)->html();

    expect($html)->toContain('data-testid="widget-layout-edit"')
        ->and($html)->not->toContain('data-testid="widget-layout-save"');
});

it('swaps them for save, cancel and reset in the mode', function () {
    $html = Livewire::test(DpCustomisablePage::class)->call('startEditingWidgets')->html();

    expect($html)->toContain('data-testid="widget-layout-save"')
        ->and($html)->toContain('data-testid="widget-layout-cancel"')
        ->and($html)->toContain('data-testid="widget-layout-reset"')
        ->and($html)->not->toContain('data-testid="widget-layout-edit"')
        // And the tray came with the mode, from the grid this page includes.
        ->and($html)->toContain('data-testid="widget-tray"');
});

it('draws none of it on a dashboard nobody may rearrange', function () {
    // Both partials answer to the same opt-in, so there is no condition in the
    // page to keep in step with one in the grid.
    $html = Livewire::test(DpSalesPage::class)->html();

    expect($html)->not->toContain('widget-layout-edit')
        ->and($html)->not->toContain('widget-tray');
});
