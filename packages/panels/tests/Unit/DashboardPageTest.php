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
