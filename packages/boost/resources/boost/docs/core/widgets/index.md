---
order: 10
summary: "What every widget shares — the base class, polling, authorization and the complete API — and which type to reach for."
---

# Widgets

A widget is one panel of a dashboard: a figure, a chart, a small table, or a view
of your own. They are ordinary PHP objects that render to HTML, composed into a
responsive grid by a host component — so a widget knows nothing about the page it
is on, and a page knows nothing about what a widget draws.

Every widget shares the same fluent builder, so heading, visibility, authorization, column span, polling, filters and deferral work identically across every type.

## Widget Types At A Glance

| Widget | Class | Best for |
| --- | --- | --- |
| **Stats overview** | `StatsOverviewWidget` | KPIs, counters, and summary metrics with optional sparklines |
| **Chart** | `ChartWidget` | Line, bar, pie, and doughnut charts powered by Chart.js |
| **Chart presets** | `LineChartWidget` / `PieChartWidget` / `DoughnutChartWidget` | Declarative `ChartWidget` presets (pie/doughnut show the legend by default) |
| **Bar chart** | `BarChartWidget` | Pure-CSS vertical/horizontal bars (finance, system) — no JavaScript |
| **Progress** | `ProgressWidget` | Pure-CSS rows of a fill against a track — how far a figure is toward its target |
| **List** | `ListWidget` | Pure-CSS feed of recent records or events, each optionally a link |
| **Table** | `TableWidget` (in `wire-table`) | A few rows of a table inside a dashboard card — no toolbar, no pagination |
| **Custom** | `CustomWidget` | Any Blade view rendered as a widget |

> Mix widget types freely inside a single `WithWidgets` dashboard — each widget controls its own column span, visibility, and refresh interval. See [Dashboard Layout](dashboards.md#dashboard-layout-withwidgets).

## Widget Base

All widgets extend `NyonCode\WireCore\Widgets\Widget` — an abstract class implementing `Htmlable`.

```php
use NyonCode\WireCore\Widgets\Widget;
```

Every widget supports:

```php
->heading(?string $heading)          // widget title
->description(?string $description)  // subtitle text
->columnSpan(int|string $span)       // grid column span (1-12, 'full')
->extraAttributes(array $attrs)      // custom HTML attributes
->hidden(bool|Closure $hidden)       // visibility control
->visible(bool|Closure $visible)     // visibility control
->permission(string $permission)     // authorization via Gate
->authorize(string $ability)         // authorization via Gate ability
->authorizeUsing(Closure $callback)  // custom authorization callback
->filter(array $options, ?string $default = null)  // a select the server resolves
->lazy(bool $condition = true)       // defer the first render
->headerActions(array $actions)      // buttons in the widget header
```

Widgets render via Blade views and support `toHtml()` / `__toString()` for direct output.

---

## Polling

All widgets support auto-refresh via Livewire polling.

```php
use NyonCode\WireCore\Widgets\Concerns\HasPolling;
```

### Usage

```php
StatsOverviewWidget::make()
    ->pollingInterval('30s')
    ->stats([...])

ChartWidget::make()
    ->pollingInterval('60s')
    ->pollingOnlyVisible()            // pause polling when widget is off-screen
```

### Polling API

```php
->pollingInterval(?string $interval)       // '5s', '10s', '30s', '60s', etc.
->getPollingInterval(): ?string
->isPolling(): bool
->pollingOnlyVisible(bool $only = true)    // only poll when visible in viewport
->isPollingOnlyVisible(): bool
->getPollingDirective(): ?string           // returns wire:poll directive string
```

> **Polling is visibility-aware by default.** `pollingOnlyVisible` defaults to `true`, so widgets use `wire:poll.visible` and pause requests while scrolled out of view. Call `->pollingOnlyVisible(false)` to keep refreshing off-screen.

### A tick refreshes the widget, not the dashboard

A plain `wire:poll` is `$refresh` — Livewire renders the whole component. On a
dashboard that means one polling widget re-renders every other widget beside it,
and any table sharing the page: measured on a 12-widget grid, **6.5 ms and
57 219 B** to deliver one widget's **3 940 B**.

So the tick names the widget instead. Each widget carries a key, and the grid
anchors the polling one:

```blade
<div wire:poll.30s.visible="refreshWidget('w1')">   {{-- [tl! focus] --}}
    <div wire:partial="widget-w1"> … </div>         {{-- [tl! focus] --}}
</div>
```

`WithWidgets::refreshWidget()` renders that widget alone and the response carries
only its markup. Nothing to configure — it is how a polling widget behaves.

Keys are derived from the widget's position in `getWidgets()`, so hiding a widget
does not renumber the rest. Where the declaration's order is likely to change,
name the key yourself:

```php
StatsOverviewWidget::make()
    ->key('revenue')               // [tl! focus]
    ->pollingInterval('30s')
    ->stats([...])
```

If the key names nothing on the next request — the widget was hidden, stopped
polling, or was removed — the tick falls back to a full render rather than
answering with half a page.

> **The grid ships wire-core's bundle when a widget polls.** The anchor is only
> an attribute; the code that applies the region lives in `wire-core-dropdown.js`.
> A dashboard with no dropdown, modal or table has no other reason to load it, so
> the grid asks for it — otherwise the response would arrive and nothing on the
> page would change.

---

## Filters

A widget can carry a select that narrows what it shows, and the selection is
resolved **on the server** — which is the only place it can be, because the
closure that turns a filter key into data is PHP.

```php
ListWidget::make()
    ->heading('Recent orders')
    ->filter(['week' => 'This week', 'month' => 'This month'], 'week')   // [tl! focus]
    ->items(fn (?string $filter) => $this->orders($filter))              // [tl! focus]
```

`filter()` is on the widget base, so every type has it: a chart's datasets, a
list's entries and a progress board's rows all take the same closure shape.

### How the round trip works

The select calls `filterWidget()` on the host component. The host records the
choice against the widget's key, pushes it back into the widget before anything
renders, and answers with **that widget's markup alone** — the same `wire:partial`
region a poll tick uses:

```blade
<select wire:change="filterWidget('w0', $event.target.value)">   {{-- [tl! focus] --}}
    <option value="week" selected>This week</option>
    <option value="month">This month</option>
</select>
```

Three consequences worth knowing:

- **The selection survives.** It lives on the host (`$widgetFilters`), not on the
  widget — a widget is rebuilt from the declaration on every request and cannot
  remember anything.
- **A widget answers only for the options it enumerated.** The key and the value
  both travel in the request, so a value the widget never offered is ignored
  rather than handed to your `match`.
- **A widget with no filter falls back to a full render**, like any call that
  queues no region.

> **This used to do nothing.** Before 2.0 the filter lived on `ChartWidget` alone
> and was resolved in the browser: an Alpine `updateChart()` assigned
> `this.labels` and `this.datasets` back onto the chart it was constructed with,
> so changing the selection redrew the identical chart and the dataset closure
> never ran with anything but its default.

---

## Deferred Rendering

A widget whose figure costs a query the rest of the page should not wait for can
draw a placeholder first and fetch itself afterwards:

```php
StatsOverviewWidget::make()
    ->heading('Lifetime revenue')
    ->lazy()                       // [tl! focus]
    ->stats([Stat::make('Revenue', $this->lifetimeRevenue())])
```

The grid renders a skeleton card the size of the widget, `wire:init` calls
`loadWidget()` on the host, and the response carries that one widget's markup as
a `wire:partial` region. The page is interactive before the count comes back.

**Once loaded, loaded.** The host records which widgets have been fetched, so a
later poll tick, filter change or full render draws the real widget rather than
dropping back to the skeleton.

**The placeholder is a card, not a spinner** — the grid has already committed to
a layout, and a placeholder shorter than its widget makes the page jump when the
real one lands. The heading is drawn for real, because the server already knows
it, and the skeleton bars carry `aria-busy` with a live region announcing the
wait.

> **Reach for it last.** The framework's rule is to make a render *cheap* rather
> than to defer it: eager HTML keeps the full DOM, adds no open latency and needs
> no client-side re-render. `lazy()` is for a widget that is **slow**, not one
> that is merely large.

---

## Writing Your Own Widget

Two files — a class that resolves state and a Blade view that draws it — and the
generator writes both:

```bash
php artisan make:wire-widget Revenue
```

```text
app/Widgets/RevenueWidget.php
resources/views/widgets/revenue.blade.php
```

The class extends `Widget`, names the view in `viewName()` and hands it data from
`getViewData()`; `$widget` is always in scope inside the view. Everything on this
page — polling, filters, deferral, column span, authorization — works on it
without another line.

```php
namespace App\Widgets;

use App\Models\Invoice;
use NyonCode\WireCore\Widgets\Widget;

class RevenueWidget extends Widget
{
    protected function viewName(): string      // [tl! focus]
    {
        return 'widgets.revenue';              // [tl! focus]
    }

    protected function getViewData(): array    // [tl! focus:start]
    {
        return [
            'value' => Invoice::whereMonth('paid_at', now()->month)->sum('total'),
        ];
    }                                          // [tl! focus:end]
}
```

An existing view is never overwritten — re-running the generator is usually about
getting the class back, and replacing markup somebody wrote is not a trade a
generator gets to make. Pass `--force` to overwrite the class.

Both templates are publishable, so an application can change what the generator
produces:

```bash
php artisan vendor:publish --tag=wire-core::stubs
```

They land in `stubs/wire-core/`, namespaced by package so two packages shipping a
`dashboard.stub` cannot overwrite each other, and the generator reads them from
there. (`stubs/` is still consulted second, for a file put there by hand.)

For a widget that is only a Blade view with no state of its own, reach for
[`CustomWidget`](custom.md) instead — no class to write at all.

---

## Header Actions

A widget can carry buttons in its header — refresh a figure, export the rows,
mark a queue as read:

```php
use NyonCode\WireCore\Actions\Action;

StatsOverviewWidget::make()
    ->heading('Open tickets')
    ->headerActions([                                              // [tl! focus:start]
        Action::make('refresh')
            ->label('Refresh')
            ->icon('outline:arrow-path')
            ->action(fn () => $this->recount()),
        Action::make('report')->label('Full report')->url(route('tickets.report')),
    ])                                                             // [tl! focus:end]
    ->stats([Stat::make('Waiting', $this->waiting())])
```

`headerActions()` is the same vocabulary a schema section header and an infolist
entry use — one owner, `Foundation\Concerns\HasActions` — so an action you wrote
for one of those surfaces works here unchanged. Every widget type draws them, in
its own header.

### What a widget action is, and what it is not

It runs its **callback**, on the server, and the widget is re-rendered
afterwards — the one region, like a poll tick. It is not the action *lifecycle*:

| Works | Does not |
| --- | --- |
| `->action(fn () => …)` | `->requiresConfirmation()` |
| `->url(…)`, `->openUrlInNewTab()` | `->form([...])`, `->slideOver()`, `->modal()` |
| `->label()`, `->icon()`, `->color()`, `->tooltip()` | `->wizard()` |
| `->hidden()` / `->visible()` | mounting, and anything that resumes into a mounted action |

An action that *removes* its own widget — a "Dismiss" button whose callback
flips what the widget's `visible()` reads — is answered with a full render
instead. A partial can replace an element but not delete one.

That is the same bargain an infolist entry's actions strike, and for the same
reason: a widget is rebuilt from its declaration on every request, so the button
carries a *name* rather than a closure and there is nothing mounted to resume
into. An action that has to ask before it acts belongs on the page — a host
composing `WithActions` owns a modal host — or inside a
[`CustomWidget`](custom.md) whose view renders `<x-wire-actions::button>`.

### How it reaches an Action at all

Worth one paragraph, because it is the framework's own layering rule in
miniature. `Widgets` and `Actions` are sibling modules and neither may import
the other, so nothing in the widget host knows what an `Action` is: the widget
resolves the action by name and answers with `Foundation\Contracts\ActionContract`,
and `Foundation\Contracts\RunsComponentActions` — bound in the container,
implemented on the Actions side — is what runs the callback. A widget in a
package that does not require the Actions module still compiles; its buttons
simply have nothing to run.

---

<a id="customisable-dashboards"></a>

## Customisable Dashboards

A dashboard can let each user decide which widgets are on it, in what order, and
how big each one is. It is **off by default**, and a dashboard that says nothing
behaves exactly as it does today — no store is consulted and the declaration is
the layout.

```php
class SalesDashboard extends Dashboard
{
    public function customisable(): bool   // [tl! focus]
    {
        return true;                       // [tl! focus]
    }

    public function widgets(): array
    {
        return [/* … */];
    }
}
```

The layout is stored per `(dashboard key, user)`, through the same
[preference store](../../table/advanced.md#column-toggling) a table's column
layout uses — configured separately, under `wire-core.preferences`, because
whether a dashboard layout is worth a database row is its own question. With no
driver configured a dashboard still works and simply forgets.

### What is stored

An ordered list of what is *placed*, each entry with a width and a height:

```php
['widgets' => [
    ['key' => 'revenue', 'w' => 2, 'h' => 1],
    ['key' => 'orders',  'w' => 1, 'h' => 2],
]]
```

Position comes from the order and size from two integers; CSS Grid packs the
result. There are no coordinates, so there is nothing to collide and no hole to
re-pack — and no way to pin a widget to an absolute cell. `w` is the column span
(1–4) and `h` the row span (1–6).

### The rules worth knowing

- **The list is what is placed.** A declared widget whose key is not in it is
  not on the dashboard — it is available to put back. One rule doing two jobs,
  rather than a `hidden` flag beside the order that could disagree with it.
- **An empty list is not the same as no layout.** Nothing stored means the
  declaration decides, which is what a user who has never touched this dashboard
  must see. A stored empty list means somebody took everything off, and they are
  entitled to an empty dashboard.
- **A layout is a preference, never a grant.** A widget the layout places and
  `visible()` or `permission()` hides stays hidden.
- **A key the declaration no longer has is dropped.** A layout cannot conjure
  back a widget that was renamed or removed by whoever owns the dashboard.
- **The bag arrives from a request**, so a size outside the grid is clamped and a
  key listed twice is placed once, at its first position.

### Height needs a row to mean something

A `row-span-2` tile needs rows of a known height, so a grid that has one gets a
row baseline and its tiles scroll inside themselves rather than stretching the
row. A grid where nothing spans rows never gets that baseline — a card there is
still as tall as its contents, exactly as before.

### Rearranging it

Editing is a **mode**, not a permanent set of handles. `Customise` reveals the
drag handles and the size steppers; `Save` keeps the arrangement, `Cancel`
throws it away, `Reset` forgets the layout entirely and goes back to what the
dashboard declares.

The host trait gives you the methods; the chrome around the grid is yours,
because it belongs to whatever renders the dashboard rather than to the grid:

```blade
@if($editingWidgets)
    <button wire:click="saveWidgetLayout">Save layout</button>       {{-- [tl! focus] --}}
    <button wire:click="cancelEditingWidgets">Cancel</button>        {{-- [tl! focus] --}}
    <button wire:click="resetWidgetLayout">Reset to default</button> {{-- [tl! focus] --}}
@else
    <button wire:click="startEditingWidgets">Customise</button>      {{-- [tl! focus] --}}
@endif

@include('wire-core::widgets.widget-grid', [
    'widgets' => $this->getVisibleWidgets(),
    'columns' => 2,
    'editing' => $editingWidgets,
])
```

**Nothing is written until Save.** The arrangement lives in a draft on the
component while the mode is open, which is what gives Cancel something to throw
away — a live drag would persist on every drop, so a stray grab would overwrite a
layout somebody was happy with and nothing could undo it.

**The drag costs no JavaScript.** It is `x-sort`, Livewire's own Alpine plugin,
which carries SortableJS and a drag ghost with it. The server places the tile: a
drop reports "this key, this position", the draft is reordered and the grid
re-renders. Cells carry a `wire:key`, so the morph pairs them by key rather than
position and the dropped DOM cannot disagree with the answer.

**Resizing is steppers, not a drag corner.** A span is 1–4 columns and 1–6 rows,
so there are eleven reachable sizes and a button says which one you are getting.

### Edit-mode API

```php
->startEditingWidgets(): void      // open the mode, from the current arrangement
->saveWidgetLayout(): void         // keep it
->cancelEditingWidgets(): void     // discard the draft
->resetWidgetLayout(): void        // forget the layout; back to the declaration
->moveWidget(string $key, int $position): void
->resizeWidget(string $key, int $width, int $height): void
public bool $editingWidgets
public array $widgetLayoutDraft
```

All of them are no-ops on a dashboard that never opted in — they are public
Livewire methods, so the browser can call them whenever it likes.

### The tray

The widgets a user can put on the dashboard but has not. It appears with edit
mode, and it is one drag with the grid — a tile dragged out goes back to the
tray, one dragged in goes on the dashboard. Buttons do the same two things for
anybody not using a pointer.

```php
StatsOverviewWidget::make()
    ->key('revenue')
    ->heading('Revenue')
    ->group('Money')            // [tl! focus]
    ->sizes([[2, 1], [4, 2]])   // [tl! focus]
    ->stats([Stat::make('Total', $this->revenue())])
```

**`group()` is the tray's heading, and nothing else.** It does not group anything
on the dashboard, where the user's own order decides. Widgets that name no group
come first, in one plain run — one group is a heading over everything, which is a
heading that says nothing.

**`sizes()` narrows what the widget is offered at**, as `[width, height]` pairs.
A sparkline row is not a 1×1 tile and a single figure is not a 4×6 one, and
letting a user find that out by dragging is worse than not offering it. The first
pair is the size the widget arrives at when it is added. Declaring none leaves
the whole grid — 1–4 columns by 1–6 rows.

The pairs are the *offer*, not a promise about storage: a layout that arrives
carrying a size outside them is still clamped to the grid rather than rejected,
because a stored layout can outlive the declaration that shaped it.

**"Removed" and "available" are the same state.** Nothing records that a widget
was taken off — it is simply not in the layout any more, which is what puts it in
the tray. One rule doing two jobs, so the two cannot disagree.

A widget a policy hides is never offered: a tray listing something that vanishes
when you add it is worse than one that does not list it.

### Tray API

```php
->group(?string $group)                    // the tray heading this widget is offered under
->sizes(array $sizes)                      // [[w, h], …] — the sizes it may take
->getGroup(): ?string
->getSizes(): array
->getDefaultSize(): array                  // the first offered pair, or [1, 1]
```

on the host:

```php
->placeWidget(string $key, int $position): void   // add, or move if already there
->removeWidget(string $key): void
->getAvailableWidgets(): array                    // group => widgets, for the tray
->widgetGridData(?int $columns = null): array     // everything the grid view needs
```

`widgetGridData()` is one call rather than five keys to assemble, because four of
them have to agree: a grid rendered without `available` draws an empty tray, and
one rendered without `trayGroup` puts the tray and the grid in different drag
groups so a tile cannot cross between them. Neither is an error anywhere.

```php
public function render()
{
    return view('wire-core::widgets.widget-grid', $this->widgetGridData(2));
}
```

### Ready-made controls

The methods are yours to call from wherever your layout puts buttons — but the
common case ships:

```blade
@include('wire-core::widgets.partials.widget-layout-controls')
@include('wire-core::widgets.widget-grid')
```

Both render nothing on a dashboard nobody may rearrange, so including them
unconditionally is safe and there is no condition to keep in step. `DashboardPage`
in wire-panels already includes both, so a declared dashboard that says
`customisable()` is rearrangeable with no view of your own at all.

There is no separate package for this. The store is wire-core's, the page is
wire-panels', and the widgets are the application's — a module would have owned
nothing, and "optional" is already what `customisable()` and the driver config
mean.

### What the whole thing needs to work

```php
// config/wire-core.php — or leave it, and layouts live for the page only
'preferences' => ['default' => 'database'],
```

```bash
php artisan vendor:publish --tag="wire-core::migrations"   # for the database driver
php artisan migrate
```

```php
class SalesDashboard extends Dashboard
{
    public function customisable(): bool { return true; }

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()->key('revenue')->heading('Revenue')  // [tl! focus]
                ->group('Money')->sizes([[2, 1], [4, 2]])                    // [tl! focus]
                ->stats([Stat::make('Total', $this->revenue())]),
        ];
    }
}
```

Every widget on a customisable dashboard needs its own `key()`. Without one the
key is derived from the widget's *position*, and a stored layout addresses
widgets by key — so inserting a widget at the top of the declaration would make
every saved layout describe different widgets than it did yesterday, while the
page still renders. A customisable dashboard refuses rather than letting that
happen.

---

## Authorization

Widgets inherit authorization from `HasVisibility` which uses the `HasAuthorization` trait. See [Authorization](#authorization) for details.

```php
StatsOverviewWidget::make()
    ->permission('view-dashboard-stats')
    ->stats([...])

ChartWidget::make()
    ->authorize('view-revenue-chart')
    ->heading('Revenue')

CustomWidget::make()
    ->authorizeUsing(fn ($user) => $user->hasRole('manager'))
    ->view('dashboard.manager-panel')
```

Unauthorized widgets are automatically excluded from `getVisibleWidgets()`.

---

## Widget API Reference

### Widget (base class)

```php
Widget::make(): static                              // static factory
->heading(?string $heading): static
->getHeading(): ?string
->description(?string $description): static
->getDescription(): ?string
->key(string $key): static
->getKey(): ?string
->usesPartialAnchor(): bool                         // true when a tick, a load or a filter targets it
->render(): View
->toHtml(): string
```

Inherited from traits:

```php
->columnSpan(int|string $span): static       // HasColumnSpan
->getColumnSpan(): int|string

->rowSpan(?int $span): static           // HasRowSpan — 1–6, null is one row sized by content
->getRowSpan(): ?int
->spansRows(): bool

->group(?string $group): static             // tray heading it is offered under
->sizes(array $sizes): static               // [[w, h], …] — the sizes it may take
->getGroup(): ?string
->getSizes(): array
->getDefaultSize(): array

->extraAttributes(array $attrs): static      // HasExtraAttributes
->getExtraAttributes(): array

->pollingInterval(?string $interval): static // HasPolling
->pollingOnlyVisible(bool $only = true): static

->lazy(bool $condition = true): static       // CanBeLazy
->isLazy(): bool

->filter(array $options, ?string $default = null): static   // HasWidgetFilter
->activeFilter(?string $filter): static
->applyFilter(?string $filter): static       // ignores a key the widget does not offer
->getFilterOptions(): ?array
->getActiveFilter(): ?string
->hasFilter(): bool

->items(array|Closure $items): static        // HasWidgetItems — fn (?string $filter): array
->emptyState(?string $message): static
->getItems(): array
->hasItems(): bool
->getEmptyState(): string

->headerActions(array $actions): static      // HasActions — array<ActionContract>
->actions(array $actions): static            // the same list, under its canonical name
->action(ActionContract $action): static     // append one
->getActions(): array                        // the visible ones, in declaration order
->hasActions(): bool
->getFieldAction(string $name): ?ActionContract
->getRenderableActions(): array          // the ones with somewhere to dispatch to — a key is all it takes
->hasRenderableActions(): bool
->getActionExpression(ActionContract $action): ?string

->hidden(bool|Closure $hidden): static       // HasVisibility + HasAuthorization
->visible(bool|Closure $visible): static
->permission(?string $permission): static
->authorize(?string $ability): static
->authorizeUsing(?Closure $callback): static
->isVisible(): bool
->isAuthorized(): bool
```

## Blade Components

```blade
<x-wire::widget-grid :widgets="$widgets" :columns="2" />
```

The views each widget renders, publishable with
`vendor:publish --tag=wire-core::views`:

```text
wire-core::widgets.stats-overview
wire-core::widgets.chart
wire-core::widgets.bar-chart
wire-core::widgets.bar-chart.vertical-finance
wire-core::widgets.bar-chart.vertical-system
wire-core::widgets.bar-chart.horizontal-system
wire-core::widgets.progress
wire-core::widgets.list
wire-core::widgets.custom
wire-core::widgets.widget-grid
wire-core::widgets.widget-cell
wire-core::widgets.partials.widget-header
wire-core::widgets.partials.widget-filter
wire-core::widgets.partials.widget-actions
wire-core::widgets.partials.widget-placeholder
wire-core::widgets.partials.list-item
```

## In This Section

| Page | What it covers |
| --- | --- |
| [Stats](stats.md) | `StatsOverviewWidget` and the `Stat` value object — figures with a trend |
| [Charts](charts.md) | `ChartWidget`, `BarChartWidget` and `ChartItem` |
| [Progress](progress.md) | `ProgressWidget` and the `ProgressItem` value object — a fill against a target |
| [Lists](list.md) | `ListWidget` and the `ListItem` value object — a short feed of records or events |
| [Tables And Custom Views](custom.md) | `TableWidget` and `CustomWidget` |
| [Dashboards](dashboards.md) | Composing widgets on a component, and declaring a dashboard as an owner |

## Related

- [Panels: Pages](../../panels/pages.md) — `DashboardPage`, the page that renders a declared dashboard
- [Actions](../actions/index.md) — buttons a widget can carry
- [Authorization](../../start/authorization.md) — the gate a widget's visibility consults
- [Tables](../../table/overview.md) — what a table widget embeds
