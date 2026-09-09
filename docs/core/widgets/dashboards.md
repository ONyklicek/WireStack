---
order: 50
summary: "Composing widgets on a component with `WithWidgets`, and declaring a dashboard as an owner the menu and router can find."
---

# Dashboards

There are two ways to put widgets on a screen, and they are the same split a
resource makes: write them on the component, or declare a **dashboard** that
names them and let a page render it. The second one is what puts an entry in the
menu and a URL in the router without either of them learning what a widget is.

## Dashboard Layout (WithWidgets)

Use the `WithWidgets` trait on a Livewire component to compose a widget dashboard.

```php
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Contracts\HasWidgets;
```

### Usage

```php
class Dashboard extends Component implements HasWidgets
{
    use WithWidgets;

    protected function getWidgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->columns(4)
                ->stats([
                    Stat::make('Users', User::count()),
                    Stat::make('Orders', Order::count()),
                    Stat::make('Revenue', '$' . number_format(Order::sum('total'), 2)),
                    Stat::make('Products', Product::count()),
                ]),

            ChartWidget::make()
                ->heading('Monthly Revenue')
                ->type('line')
                ->columnSpan(2)
                ->labels($this->getMonthLabels())
                ->datasets($this->getRevenueDatasets()),

            TableWidget::make()
                ->heading('Recent Orders')
                ->table(fn ($table) => $this->configureRecentOrdersTable($table)),
        ];
    }

    protected function getWidgetColumns(): int
    {
        return 2;  // 2-column grid layout
    }
}
```

### Blade Template

Render the dashboard with the `<x-wire::widget-grid>` component.
`getVisibleWidgets()` is public and returns only the widgets that pass their
visibility and authorization checks; each widget honors its own `columnSpan()`
and polling interval inside the grid:

```blade
<div>
    <x-wire::widget-grid :widgets="$this->getVisibleWidgets()" :columns="2" />
</div>
```

Each widget is also `Htmlable`, so you can skip the component and lay them out
yourself: `@foreach ($this->getVisibleWidgets() as $widget) {{ $widget }} @endforeach`.

### WithWidgets API

```php
abstract protected function getWidgets(): array      // define widgets
protected function getWidgetColumns(): int           // grid columns (default: 2)
public function getVisibleWidgets(): array           // filtered by visibility + authorization
public function refreshWidget(string $key): void     // a poll tick, answered with that widget
public function filterWidget(string $key, string $value): void   // a filter selection
public function callWidgetAction(string $key, string $name): void // a header action
public function loadWidget(string $key): void        // a deferred widget's first render
public array $widgetFilters                          // the selection each widget is showing
public array $loadedWidgets                          // the keys already fetched
```

### The host holds the state, the widgets hold none

A widget is a plain object rebuilt from `getWidgets()` on every request, so it
cannot remember anything across a round trip. The host can, and does: it records
which filter each widget is showing and which deferred widgets have already been
fetched, then pushes both back into the widgets in `getVisibleWidgets()` —
after the keys are stamped, and before visibility is asked, so a
`visible(fn () => …)` closure can read the filter the user chose.

All three of the methods above answer with **one widget's markup**, using the
same `wire:partial` region:

| Trigger | Called by | Answers with |
| --- | --- | --- |
| `refreshWidget()` | `wire:poll` on the widget's wrapper | that widget, re-rendered |
| `filterWidget()` | `wire:change` on the widget's select | that widget, re-resolved with the new key |
| `callWidgetAction()` | `wire:click` on a header action's button | that widget, after the action has run — or a full render, when the action removed it |
| `loadWidget()` | `wire:init` on a lazy widget's wrapper | that widget, drawn for the first time |

A key that names nothing — a widget hidden since the page rendered, one that
offers no filter, one that was removed — queues no region, and the request falls
back to a full render rather than answering with half a page.

### HasWidgets Interface

```php
interface HasWidgets
{
    public function getWidgets(): array;
}
```

---

## Dashboard (declared, not a component)

`WithWidgets` above puts a dashboard *inside* a Livewire component, which is
fine until something else needs it: a component cannot be registered, listed in
a menu, or reused on a second page, and its widgets are unreachable from
anywhere but itself.

`Dashboard` holds the declaration on its own, the way a `Resource` holds a
table's:

```php
use NyonCode\WireCore\Widgets\Dashboard;   // [tl! focus:start]

final class SalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()->stats([Stat::make('Revenue', '1.2M')]),
            ChartWidget::make()->heading('Last 30 days')->type('line'),
        ];
    }

    public function columns(): int
    {
        return 3;
    }
}   // [tl! focus:end]
```

`php artisan make:wire-dashboard Sales` generates exactly that — into
`app/Dashboards/`, from a stub you can publish and change:

```bash
php artisan vendor:publish --tag=wire-core::stubs
```

A published `stubs/dashboard.stub` wins over the package's, the way Laravel's
own `stub:publish` works.

Register it the way resources are registered:

```php
// config/wire-core.php
'dashboards' => [
    App\Dashboards\SalesDashboard::class,
],
```

### Where an application registers them

Dashboards and navigation groups belong to neither a resource nor a dashboard —
they are what an application composes. wire-core ships a provider for exactly
that, the way Cashier and Fortify do:

```bash
php artisan vendor:publish --tag=wire-core::providers
```

That writes `app/Providers/WireDashboardServiceProvider.php`, which is yours to
edit: register it in `bootstrap/providers.php` and declare your dashboards and
your menu's groups in one place.

### Rendering it

`WirePanels\Resources\Pages\DashboardPage` renders one, exactly as `ListPage`
renders a resource's table — it composes `WithWidgets`, so the grid, the
visibility rules and the per-widget polling above are unchanged:

```php
use NyonCode\WirePanels\Resources\Pages\DashboardPage;

class SalesDashboardPage extends DashboardPage
{
    protected static ?string $dashboard = SalesDashboard::class;
}
```

Both paths stay first class: a page that declares its own `getWidgets()` and
names no dashboard works exactly as before. A page that declares neither refuses
to render rather than showing an empty grid, because empty reads as "no widgets"
rather than as a mistake.

### Putting it in the menu

A dashboard reaches a menu the same way a resource does — by implementing
`ProvidesNavigation`:

```php
public static function navigation(): NavigationItem
{
    return NavigationItem::make('Sales')->icon('outline:chart-bar')->group('insights');
}
```

Nothing about `Workspace` knows what a dashboard is. `DashboardRegistry` is a
`RegistrySource`, and a workspace lists whatever the catalogue hands it — which
is what lets a menu mix resources, dashboards and anything an application
registers later. The router reads the same catalogue, so a dashboard that
declares `pages()` is routed by `Route::wireResources()` like any resource. See [Resources](../../panels/navigation.md).

### Dashboard API

| Method | Returns | Purpose |
| --- | --- | --- |
| `widgets(): array` | `array<int, Widget>` | The widgets, in layout order. Required |
| `columns(): int` | `int` | Grid columns; defaults to 2 |
| `static key(): string` | `string` | Identity, derived from the class name minus `Dashboard` |
| `static label(): string` | `string` | Human name; the page's default heading |

---

## Adding A Widget To A Dashboard You Do Not Own

A dashboard shipped by an installed [module](../../panels/modules.md) declares its
widgets inside the package, so an application adds to it through the
[`widget.configuring` hook](../plugins/hooks.md):

```php
$manager->hook(Hook::WidgetConfiguring, function (WidgetConfiguringPayload $payload) {
    $payload->widgets[] = StatsOverviewWidget::make()->stats([Stat::make('Open', '12')]); // [tl! focus]

    return $payload;
}, for: 'sales');
```

`for:` names the key the dashboard registered under — `DashboardPage` answers with
it. A page that declares its own widgets shows nothing registered and is scoped by
class instead.

**It runs before the keys are stamped and before visibility filters the list**, and
both halves matter: a widget's key comes from its index in the unfiltered list, so
one added afterwards would carry none — and be unreachable by a poll tick — or take
a key another widget already answers to; and an added widget's own `visible()` is
honoured rather than skipped.

## Related

- [Widgets](index.md) — what is being composed
- [Panels: Pages](../../panels/pages.md) — `DashboardPage`, the component that renders a declared one
- [Panels: Navigation](../../panels/navigation.md) — how a dashboard reaches the menu
- [Panels: Modules](../../panels/modules.md) — declaring an area's dashboards with its resources
