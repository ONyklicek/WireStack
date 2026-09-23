<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

use Illuminate\Support\Str;
use NyonCode\WireCore\Core\Resources\Contracts\ProvidesNavigation;
use NyonCode\WireCore\Foundation\Registration\Contracts\RegistrySource;
use NyonCode\WireCore\Widgets\Contracts\HasWidgets;
use NyonCode\WireCore\Widgets\Support\DashboardFilterState;

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
 * {@see RegistrySource}, so `Workspace` lists it without ever knowing what a
 * dashboard is, and since ADR 0026 the router and the search palette read the
 * same catalogue. A dashboard that also declares `ProvidesPages::pages()` is
 * routed by `Route::wireResources()` like any resource.
 */
abstract class Dashboard implements HasWidgets
{
    private ?DashboardFilterState $filterState = null;

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
     * Whether a user may rearrange this dashboard.
     *
     * False, and that is the whole of the opt-in: a dashboard says nothing and
     * behaves exactly as every dashboard behaves today — the declaration below
     * is the layout, no store is consulted, and nothing about the rendered grid
     * changes. Saying `true` makes {@see key()} the key a user's layout is
     * stored under.
     *
     * Opt-in rather than opt-out because the two are not symmetric. A dashboard
     * that quietly became rearrangeable would start reading a store an
     * application never configured, and — worse — would let a user hide a widget
     * the application put there on purpose. Where that is wanted, it is a
     * sentence to write.
     */
    public function customisable(): bool
    {
        return false;
    }

    /**
     * Whether a user may keep several arrangements of this dashboard, each under
     * a name.
     *
     * False, and separate from {@see customisable()} on purpose: rearranging a
     * dashboard and keeping *more than one* arrangement are different wishes,
     * and the second adds a switcher above the grid that a dashboard with one
     * layout does not want. The same split a table makes between
     * `rememberColumns()` and `savedViews()`, riding the same store.
     *
     * Means nothing on a dashboard that is not customisable — there is no
     * arrangement to name — so the page asks for both.
     */
    public function savedLayouts(): bool
    {
        return false;
    }

    /**
     * What a user sees before arranging anything; null places everything declared.
     *
     * A spec of widget keys in order, each optionally with a size — a pair or
     * one of the widget's named sizes:
     *
     *   ['revenue', 'orders' => [2, 1], 'queue' => 'L']
     *
     * Everything declared and not listed starts in the tray, and "Reset" comes
     * back here rather than to the whole declaration. An instance method, asked
     * on every render, so it may depend on who is looking — the test bench wants
     * its queue, the office wants the money. Means nothing on a dashboard that
     * is not {@see customisable()}. See `Support\DefaultWidgetLayout`.
     *
     * @return array<int|string, mixed>|null
     */
    public function defaultLayout(): ?array
    {
        return null;
    }

    /**
     * Whether a change in edit mode is stored at once rather than on Save.
     *
     * False, which keeps the edit mode's point: a stray drag cannot overwrite a
     * layout until somebody says so. A dashboard used as a working tool all day,
     * where a forgotten Save is the worse loss and "Reset" is the undo, says
     * true — its controls then offer Done instead of Save and Cancel.
     */
    public function autosave(): bool
    {
        return false;
    }

    /**
     * The most widgets a user may place on this dashboard; null for no limit.
     *
     * Bounds what a user adds from the tray. A dashboard with forty tiles is
     * unreadable and costs forty widgets of queries per render; the tray says
     * when the limit is reached rather than letting the page get there.
     */
    public function maxWidgets(): ?int
    {
        return null;
    }

    /**
     * Filters over the whole dashboard, read by every widget.
     *
     * Each is one selection in the page's address that `widgets()` narrows by,
     * through {@see filter()} — so the whole dashboard answers the same
     * question at once rather than each widget on a slice of its own.
     *
     * @return array<int, DashboardFilter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * The resolved filter values this dashboard builds its widgets for.
     *
     * Set by whatever renders the dashboard, which is the one place the
     * selection lives; the declaration only reads it.
     */
    public function withFilterState(DashboardFilterState $state): static
    {
        $this->filterState = $state;

        return $this;
    }

    /** The value of one of this dashboard's filters, for `widgets()` to narrow by. */
    protected function filter(string $name): ?string
    {
        return ($this->filterState ?? DashboardFilterState::resolve($this->filters(), []))->value($name);
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
        return Str::kebab(self::baseName());
    }

    /** Human name for the page and, unless the entry says otherwise, for its menu row. */
    public static function label(): string
    {
        return Str::headline(self::baseName());
    }

    /**
     * The class name without its namespace and without a trailing "Dashboard".
     *
     * One owner for the two derivations above, which is not tidiness: they have
     * to agree. A key derived one way and a label derived another drift the
     * moment someone renames the class, and the pair is what the menu shows and
     * what the application routes on.
     *
     * The fallback covers a class named exactly `Dashboard`, where stripping the
     * suffix leaves nothing to call it.
     *
     * Called as `self::`, not `static::`: this is the derivation, not an
     * extension point — a subclass that wants a different key or label overrides
     * the two public methods above, where the intent is visible.
     */
    private static function baseName(): string
    {
        $base = class_basename(static::class);

        return Str::of($base)->beforeLast('Dashboard')->value() ?: $base;
    }
}
