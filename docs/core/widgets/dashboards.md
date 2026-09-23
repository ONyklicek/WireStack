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
use NyonCode\WireCore\Widgets\ChartWidget;
use NyonCode\WireCore\Widgets\Concerns\WithWidgets;
use NyonCode\WireCore\Widgets\Contracts\HasWidgets;
use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Widgets\TableWidget;   // [tl! focus]
```

**`TableWidget` is the one that is not wire-core's.** It draws rows of a table
inside a card, and the engine that draws them lives in `wire-table` — so the
class does too, and an application without that package composes the other
widget kinds exactly as below. See [Tables And Custom Views](custom.md).

### Usage

```php
class Dashboard extends Component implements HasWidgets
{
    use WithWidgets;

    protected function getWidgets(): array   // [tl! focus:start]
    {
        return [
            StatsOverviewWidget::make()
                ->columns(4)
                ->stats([
                    Stat::make('Users', (string) User::count()),
                    Stat::make('Orders', (string) Order::count()),
                    Stat::make('Revenue', "$" . number_format(Order::sum('total'), 2)),
                    Stat::make('Products', (string) Product::count()),
                ]),

            ChartWidget::make()
                ->heading('Monthly Revenue')
                ->type('line')
                ->columnSpan(2)
                ->labels($this->getMonthLabels())
                ->datasets($this->getRevenueDatasets()),

            TableWidget::make()
                ->heading('Recent Orders')
                ->limit(5)
                ->table(fn (Table $table): Table => $this->configureRecentOrdersTable($table)),
        ];
    }   // [tl! focus:end]

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
protected function getDashboardFilters(): array      // filters over the whole dashboard (default: none) [tl! focus:start]
public function dashboardFilter(string $name): ?string          // one filter's value, for getWidgets()
public function setDashboardFilter(string $name, string $value): void // what the filter bar calls
public function resetDashboardFilters(): void        // every filter back to its default
public array $dashboardFilters                       // the selection, in the address as ?dashboard[…] [tl! focus:end]
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

`php artisan make:wire-dashboard Sales` generates **two** files:

```text
app/Dashboards/SalesDashboard.php          the declaration
app/Livewire/Dashboards/ShowSales.php      the page that mounts it
```

Two, because one of them cannot be opened. A dashboard that declares no pages is
routed nowhere — it appears in the menu as an entry with no link and answers 404
at every address — so a generator that wrote only the declaration handed back
something that looked finished and was not.

The page is wire-panels' class, so wire-panels generates it: the command calls
`make:wire-dashboard-page` when that package is installed, and says what to
install when it is not. `--no-page` writes the declaration alone; the declared
`pages()` stays either way, since nothing reads it until something routes.

Both templates are stubs you can publish and change:

```bash
php artisan vendor:publish --tag=wire-core::stubs     # the dashboard
php artisan vendor:publish --tag=wire-panels::stubs   # the page
```

A published `stubs/wire-core/dashboard.stub` wins over the package's, the way
Laravel's own `stub:publish` works.

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
| `customisable(): bool` | `bool` | Whether each user may rearrange it; false, and that is the whole opt-in |
| `savedLayouts(): bool` | `bool` | Whether each user may keep several arrangements, each under a name; needs `customisable()` too |
| `defaultLayout(): ?array` | `array\|null` | What a user sees before arranging anything; the rest starts in the tray. Null places everything declared |
| `autosave(): bool` | `bool` | Whether a change in edit mode is stored at once; false keeps Save and Cancel |
| `maxWidgets(): ?int` | `int\|null` | The most widgets a user may place; null for no limit |
| `filters(): array` | `array<int, DashboardFilter>` | Filters over the whole dashboard, read in `widgets()` through `filter()` |
| `withFilterState(DashboardFilterState $state): static` | `static` | Set by the page before `widgets()`; the declaration only reads it |
| `filter(string $name): ?string` | `string\|null` | Protected. One filter's value — its default until the page says otherwise |
| `static key(): string` | `string` | Identity, derived from the class name minus `Dashboard` |
| `static label(): string` | `string` | Human name; the page's default heading |

### Letting each user rearrange it

`customisable()` is one sentence here and a whole feature behind it: the widgets
a user placed, in their order and at their sizes, stored per user and per
dashboard. `DashboardPage` turns it into the key the layout is stored under and
includes the Customise / Save / Cancel / Reset controls, so a declared dashboard
needs no view of its own. Every widget on one needs its own `key()`.

`defaultLayout()`, `autosave()` and `maxWidgets()` shape that feature — what a
newcomer sees, whether a change is stored at once, how many widgets fit. See
[Widgets → Customisable dashboards](index.md#customisable-dashboards) for the
store, the tray, `group()`, `sizes()` and those three.

### Filters over the whole dashboard

A widget's own `filter()` narrows that widget. A dashboard read as one picture
wants the opposite: one selection — "this month", "this customer" — that every
widget answers, so two figures side by side answer the same question.

```php
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\DashboardFilter;

final class ProductionDashboard extends Dashboard
{
    public function filters(): array                                          // [tl! focus:start]
    {
        return [
            DashboardFilter::make('period')->label('Period')->buttons()
                ->options(['week' => 'This week', 'month' => 'This month', 'all' => 'All'])
                ->default('month'),
            DashboardFilter::make('customer')->label('Customer')->placeholder('All customers')
                ->options(fn (): array => Customer::withWorkInProgress()->pluck('name', 'id')->all()),
        ];
    }                                                                         // [tl! focus:end]

    public function widgets(): array
    {
        $metrics = ProductionMetrics::for($this->filter('period'), $this->filter('customer')); // [tl! focus]

        return [
            StatsOverviewWidget::make()->key('made')->heading('Made')
                ->stats([Stat::make('Pieces', (string) $metrics->made())]),
            StatsOverviewWidget::make()->key('server')->heading('Accounting server')
                ->ignoresDashboardFilters()                                   // [tl! focus]
                ->stats([Stat::make('Status', $this->serverStatus())]),
        ];
    }
}
```

**The selection lives on the page and in the address.** `DashboardPage` holds
it in `$dashboardFilters`, which is in the query string as `?dashboard[period]=week`
— so a dashboard can be sent as a link exactly as its sender saw it, and a reload
keeps it. Only values that differ from a default are kept, so an unfiltered
dashboard has a clean address. The property is locked: the filter bar changes it
through `setDashboardFilter()`, and whatever the address carried is checked
before anything reads it.

**A value is kept only when the filter offered it.** Everything else — a stale
link, a typo, an id from before the options changed — resolves to the filter's
default rather than reaching the query that reads it. A default of `null` means
"narrows nothing" and is drawn as the placeholder.

**The page hands the resolved values to the dashboard before `widgets()` runs**
(`withFilterState()`), and `filter()` reads them; a dashboard built without a page
reads its defaults. A hand-written `WithWidgets` host declares
`getDashboardFilters()` and reads `$this->dashboardFilter('period')` in
`getWidgets()` the same way.

**A widget that cannot be narrowed says so.** `ignoresDashboardFilters()` puts a
"Not filtered" mark on it while any filter differs from its default — the state
of an external server has no customer, and a reader comparing it with its
filtered neighbours needs to know.

**Where it is drawn.** `DashboardPage` puts the filter bar beside the layout
controls. A host of your own includes it where its layout wants it:

```blade
@include('wire-core::widgets.partials.widget-filters')   {{-- renders nothing without filters --}}
@include('wire-core::widgets.widget-grid')
```

Buttons for a filter declared `buttons()` — a handful of periods read at a
glance — and a select for the rest, where the options are records. A "Clear
filters" link appears only while something is narrowed.

### DashboardFilter API

```php
DashboardFilter::make(string $name)
->label(string|Closure|null $label)            // defaults to the name, headlined
->options(array|Closure $options)              // value => label; a closure is resolved when needed
->default(int|string|null $default)            // the value when nothing is chosen; null narrows nothing
->placeholder(string|Closure|null $placeholder) // the select's empty option — default: the label
->buttons(bool $buttons = true)                // a row of buttons instead of a select
->getOptions(): array                          // keyed by the value as the address carries it
->getDefault(): ?string
->isButtons(): bool
->resolve(mixed $value): ?string               // an offered value, else the default
```

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
