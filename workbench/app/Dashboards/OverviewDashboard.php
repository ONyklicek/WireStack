<?php

declare(strict_types=1);

namespace Workbench\App\Dashboards;

use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Core\Resources\Navigation\NavigationItem;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use Workbench\App\Models\Document;
use Workbench\App\Models\Invoice;
use Workbench\App\Models\Task;

/**
 * The workbench's dashboard, on the same real data its resources list.
 *
 * V2.6 step 3's own gate: a dashboard has to reach a menu and a page without
 * being a resource and without `Workspace` learning what a dashboard is. Three
 * widgets over the three workbench models is enough to show that, and every one
 * of them counts real rows — a dashboard of fixed numbers would render
 * identically whether or not the declaration was ever reached.
 */
final class OverviewDashboard extends Dashboard implements ProvidesNavigation
{
    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->heading('Billing')
                ->stats([
                    Stat::make('Invoices', (string) Invoice::query()->count()),
                    Stat::make('Overdue', (string) Invoice::query()->where('status', 'overdue')->count()),
                ]),
            StatsOverviewWidget::make()
                ->heading('Work')
                ->stats([
                    Stat::make('Open tasks', (string) Task::query()->where('completed', false)->count()),
                    Stat::make('Documents', (string) Document::query()->count()),
                ]),
        ];
    }

    public function columns(): int
    {
        return 2;
    }

    /**
     * In a group of its own, sorted above both resource groups — so the menu
     * shows an entry that is not a resource, in a heading that contains nothing
     * else, ordered against every registration order there is.
     *
     * Named "Overview" rather than "Operations" on purpose: the key a dashboard
     * derives is its class name minus the suffix, so an `OperationsDashboard`
     * would key itself `operations` and collide with the *group* of that name
     * in the workbench's own url map. Nothing in the framework breaks — the two
     * are different namespaces — but the page it links to would be the wrong
     * one, which is exactly the kind of thing a driver should be able to see.
     */
    public static function navigation(): NavigationItem
    {
        return NavigationItem::make('Overview')
            ->icon('outline:chart-bar')
            ->group('insights');
    }
}
