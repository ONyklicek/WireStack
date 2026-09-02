<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Resources\Contracts\NavigationSource;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Widgets\Contracts\HasWidgets;

/**
 * A page's worth of widgets, declared once and away from any component.
 *
 * The owner layer's third kind, beside `Resource` and its pages, and it exists
 * for the reason those do: composing widgets already had an owner — `WithWidgets`
 * stamps their keys, filters them by visibility, lays out the grid and answers a
 * poll tick with one widget — but that owner is a *host trait*, so a dashboard
 * was a Livewire component and nothing else. A component cannot be registered,
 * listed, put in a menu or reused on a second page, and the widgets were
 * unreachable from anywhere but the component that declared them.
 *
 * So this holds the declaration and nothing else:
 *
 *   final class SalesDashboard extends Dashboard
 *   {
 *       public function widgets(): array
 *       {
 *           return [
 *               StatsOverviewWidget::make()->stats([Stat::make('Revenue', '1.2M')]),
 *               ChartWidget::make()->heading('Last 30 days'),
 *           ];
 *       }
 *   }
 *
 * What renders it is `WirePanels\Resources\Pages\DashboardPage`, which composes
 * `WithWidgets` exactly as `ListPage` composes `WithTable`. No new runtime:
 * every widget, the grid, the polling partial and the visibility rules are the
 * ones that were already here.
 *
 * A dashboard that should appear in a menu implements
 * {@see ProvidesNavigation},
 * the same contract a resource uses, and is registered into
 * {@see DashboardRegistry} — which is a
 * {@see NavigationSource}, so
 * `Workspace` lists it without ever knowing what a dashboard is.
 */
abstract class Dashboard implements HasWidgets
{
    /**
     * The widgets on this dashboard, in the order they are laid out.
     *
     * @return array<int, Widget>
     */
    abstract public function widgets(): array;

    /**
     * @return array<int, Widget>
     */
    public function getWidgets(): array
    {
        return $this->widgets();
    }

    /**
     * Columns in the grid. The page passes this to `WithWidgets`, which owns
     * what each count means responsively.
     */
    public function columns(): int
    {
        return 2;
    }

    /**
     * A stable identity, unique among everything a menu lists.
     *
     * Derived from the class name with a trailing "Dashboard" dropped, for the
     * same reason `DescribesRecords` derives a resource's: a key and a label
     * taken from two different places drift the moment someone renames one.
     * Override it where the class name is not what the application routes on.
     */
    public static function key(): string
    {
        $base = Str::of(class_basename(static::class))->beforeLast('Dashboard')->value()
            ?: class_basename(static::class);

        return Str::kebab($base);
    }

    /** Human name for the page and, unless the entry says otherwise, for its menu row. */
    public static function label(): string
    {
        return Str::headline(
            Str::of(class_basename(static::class))->beforeLast('Dashboard')->value()
                ?: class_basename(static::class)
        );
    }
}
