<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Resources\Contracts\NavigationSource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationGroups;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Core\Resources\Workspace;
use NyonCode\WireCore\Exceptions\ResourceRegistrationException;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\DashboardRegistry;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;

/*
 * A page's worth of widgets, declared away from any component.
 *
 * What is worth pinning is not that a dashboard holds widgets — every widget
 * here was already tested — but that the declaration is reachable *without* a
 * Livewire component, which is the whole reason the type exists.
 */

final class DashSalesDashboard extends Dashboard implements ProvidesNavigation
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

    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Sales overview')->group('reports')->icon('outline:chart-bar');
    }
}

final class DashOpsDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [];
    }
}

final class DashNotADashboard {}

it('declares its widgets without a component in sight', function () {
    // The property the type exists for: before this, getWidgets() was a
    // protected method on a Livewire host, so the declaration could not be
    // registered, listed, or reused on a second page.
    $dashboard = new DashSalesDashboard;

    expect($dashboard->widgets())->toHaveCount(2)
        ->and($dashboard->getWidgets())->toHaveCount(2)
        ->and($dashboard->widgets()[0]->getHeading())->toBe('Revenue')
        ->and($dashboard->columns())->toBe(3);
});

it('falls back to the whole class name when the suffix is all there is', function () {
    // A class named exactly `Dashboard`: stripping the suffix leaves nothing, so
    // the name itself has to serve. Its own namespace because the name collides
    // with the base class anywhere else.
    require_once __DIR__.'/../../Fixtures/PlainDashboard.php';

    $class = NyonCode\WireCore\Tests\Fixtures\Dashboards\Dashboard::class;

    expect($class::key())->toBe('dashboard')
        ->and($class::label())->toBe('Dashboard');
});

it('derives a key and a label from its class name', function () {
    // Same rule as DescribesRecords, for the same reason: a key and a label
    // taken from two different places drift the moment someone renames one.
    expect(DashSalesDashboard::key())->toBe('dash-sales')
        ->and(DashSalesDashboard::label())->toBe('Dash Sales')
        ->and(DashOpsDashboard::key())->toBe('dash-ops');
});

it('defaults to two columns and no widgets is an ordinary dashboard', function () {
    expect((new DashOpsDashboard)->columns())->toBe(2)
        ->and((new DashOpsDashboard)->widgets())->toBe([]);
});

it('registers dashboards by key and answers for them', function () {
    $registry = new DashboardRegistry;
    $registry->register(DashSalesDashboard::class);
    $registry->register(DashSalesDashboard::class);   // idempotent

    expect(array_keys($registry->all()))->toBe(['dash-sales'])
        ->and($registry->has('dash-sales'))->toBeTrue()
        ->and($registry->find('dash-sales'))->toBe(DashSalesDashboard::class)
        ->and($registry->find('nothing'))->toBeNull();
});

it('refuses a class that is not a dashboard', function () {
    expect(fn () => (new DashboardRegistry)->register(DashNotADashboard::class))
        ->toThrow(ResourceRegistrationException::class);
});

it('refuses two dashboards claiming one key', function () {
    $registry = new DashboardRegistry;
    $registry->register(DashSalesDashboard::class);

    $other = new class extends Dashboard
    {
        public function widgets(): array
        {
            return [];
        }

        public static function key(): string
        {
            return 'dash-sales';
        }
    };

    expect(fn () => $registry->register($other::class))->toThrow(ResourceRegistrationException::class);
});

it('ignores config entries it cannot use', function () {
    $registry = new DashboardRegistry;
    $registry->registerMany([DashSalesDashboard::class, '', null]);
    $registry->registerMany('not an array');

    expect(array_keys($registry->all()))->toBe(['dash-sales']);
});

it('is a navigation source, so a menu lists a dashboard beside a resource', function () {
    // The layer rule made visible: Workspace is L1 and this is L2, so the menu
    // reads the registry through NavigationSource and never learns what a
    // dashboard is.
    $registry = new DashboardRegistry;
    $registry->register(DashSalesDashboard::class);
    $registry->register(DashOpsDashboard::class);

    expect($registry)->toBeInstanceOf(NavigationSource::class)
        ->and(array_keys($registry->navigableClasses()))->toBe(['dash-sales', 'dash-ops']);

    $nav = (new Workspace([$registry], new NavigationGroups))->navigation();

    // Only the one declaring an entry is in the menu; the other is registered
    // and reachable, just not listed.
    expect(array_keys($nav))->toBe(['reports'])
        ->and(array_keys($nav['reports']->getItems()))->toBe(['dash-sales'])
        ->and($nav['reports']->getItems()['dash-sales']->getLabel())->toBe('Sales overview');
});
