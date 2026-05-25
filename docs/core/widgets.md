# Widgets

The Widget module provides dashboard components — stats cards, charts, embedded tables, and custom views. Widgets live in `wire-core` and can be composed into grid layouts on any Livewire component.

---

## Table of Contents

1. [Widget Base](#widget-base)
2. [StatsOverviewWidget](#statsoverviewwidget)
3. [Stat](#stat)
4. [ChartWidget](#chartwidget)
5. [TableWidget](#tablewidget)
6. [CustomWidget](#customwidget)
7. [Polling](#polling)
8. [Dashboard Layout (WithWidgets)](#dashboard-layout-withwidgets)
9. [Authorization](#authorization)
10. [Widget API Reference](#widget-api-reference)

---

## Widget Base

All widgets extend `NyonCode\WireCore\Widgets\Widget` — an abstract class implementing `Htmlable`.

```php
use NyonCode\WireCore\Widgets\Widget;
```

Every widget supports:

```php
->heading(?string $heading)          // widget title
->description(?string $description)  // subtitle text
->lazy(bool $lazy = true)            // defer rendering
->columnSpan(int|string $span)       // grid column span (1-12, 'full')
->extraAttributes(array $attrs)      // custom HTML attributes
->hidden(bool|Closure $hidden)       // visibility control
->visible(bool|Closure $visible)     // visibility control
->permission(string $permission)     // authorization via Gate
->authorize(string $ability)         // authorization via Gate ability
->authorizeUsing(Closure $callback)  // custom authorization callback
```

Widgets render via Blade views and support `toHtml()` / `__toString()` for direct output.

---

## StatsOverviewWidget

Grid of stat cards — ideal for KPIs, counters, and summary metrics.

```php
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Stat;
```

### Basic Usage

```php
StatsOverviewWidget::make()
    ->heading('Overview')
    ->columns(3)
    ->stats([
        Stat::make('Total Revenue', '$45,231')
            ->description('12% increase')
            ->descriptionIcon('arrow-up')
            ->color('success'),

        Stat::make('New Users', '1,234')
            ->description('3% decrease')
            ->descriptionIcon('arrow-down')
            ->color('danger'),

        Stat::make('Orders', '856')
            ->description('Same as last month')
            ->color('gray'),
    ])
```

### Grid Columns

```php
->columns(int $columns)   // 1-4 columns (clamped)
```

Default is 3 columns. The grid is responsive.

### StatsOverviewWidget API

```php
->stats(array $stats)               // array of Stat instances
->getStats(): array
->columns(int $columns)             // grid columns (1-4)
->getGridColumns(): int
```

---

## Stat

Individual stat card within a `StatsOverviewWidget`.

```php
use NyonCode\WireCore\Widgets\Stat;
```

### Full Example

```php
Stat::make('Monthly Revenue', '$12,430')
    ->description('8% increase from last month')
    ->descriptionIcon('arrow-up')
    ->color('success')
    ->icon('currency-dollar')
    ->chart([7, 3, 4, 5, 6, 3, 5, 8])
    ->extraAttributes(['class' => 'ring-2 ring-green-200'])
```

### Sparkline Chart

```php
->chart(array $data)   // array of numeric data points for SVG sparkline
```

```php
Stat::make('Active Users', '2,847')
    ->chart([12, 15, 18, 14, 22, 25, 28, 32])
    ->color('primary')
```

### Stat API

```php
Stat::make(string $label, string $value)
->description(?string $description)       // secondary text
->descriptionIcon(?string $icon)          // icon next to description
->color(?string $color)                   // 'success', 'danger', 'warning', 'primary', 'gray'
->icon(?string $icon)                     // stat card icon
->chart(array $data)                      // sparkline data points (int|float)
->extraAttributes(array $attrs)           // custom HTML attributes
->getLabel(): string
->getValue(): string
->getDescription(): ?string
->getDescriptionIcon(): ?string
->getColor(): ?string
->getIcon(): ?string
->getChart(): ?array
->hasChart(): bool
```

---

## ChartWidget

Chart widget with Chart.js integration. Supports line, bar, pie, and doughnut charts.

```php
use NyonCode\WireCore\Widgets\ChartWidget;
```

### Basic Usage

```php
ChartWidget::make()
    ->heading('Revenue Over Time')
    ->type('line')
    ->labels(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'])
    ->datasets([
        [
            'label' => 'Revenue',
            'data' => [1200, 1900, 3000, 5000, 2300, 3200],
            'borderColor' => '#3B82F6',
        ],
    ])
```

### Chart Types

```php
->type('line')        // line chart (default)
->type('bar')         // bar chart
->type('pie')         // pie chart
->type('doughnut')    // doughnut chart
```

### Dynamic Data with Closures

Datasets and labels accept Closures. The active filter value is passed as argument:

```php
ChartWidget::make()
    ->heading('Sales')
    ->type('bar')
    ->filter(['2025' => '2025', '2026' => '2026'], '2026')
    ->labels(fn (?string $filter) => match($filter) {
        '2025' => ['Q1', 'Q2', 'Q3', 'Q4'],
        '2026' => ['Q1', 'Q2'],
        default => [],
    })
    ->datasets(fn (?string $filter) => [
        ['label' => 'Sales', 'data' => $filter === '2025' ? [100, 200, 150, 300] : [180, 250]],
    ])
```

### Filter Dropdown

```php
->filter(array $options, ?string $default = null)
```

Adds a dropdown filter on the widget. The selected value is passed to dataset/label closures.

```php
ChartWidget::make()
    ->heading('Revenue')
    ->filter([
        'week' => 'This Week',
        'month' => 'This Month',
        'year' => 'This Year',
    ], 'month')
```

### ChartWidget API

```php
->type(string $type)                       // 'line', 'bar', 'pie', 'doughnut'
->getType(): string
->datasets(array|Closure $datasets)        // Chart.js dataset format
->getDatasets(): array
->labels(array|Closure $labels)            // x-axis labels
->getLabels(): array
->filter(array $options, ?string $default) // dropdown filter options
->getFilterOptions(): ?array
->hasFilter(): bool
->getActiveFilter(): ?string
->activeFilter(?string $filter)            // set active filter programmatically
```

---

## TableWidget

Embeds a wire-table inside a widget. Useful for compact data views in dashboards.

```php
use NyonCode\WireCore\Widgets\TableWidget;
```

### Basic Usage

```php
TableWidget::make()
    ->heading('Recent Orders')
    ->table(fn (Table $table) => $table
        ->columns([
            TextColumn::make('number')->searchable(),
            TextColumn::make('customer.name'),
            TextColumn::make('total')->money('CZK'),
            BadgeColumn::make('status')->colors([...]),
        ])
        ->query(Order::query()->latest()->limit(10))
    )
```

### TableWidget API

```php
->table(Closure $callback)           // fn(Table $table): Table
->getTableCallback(): ?Closure
```

---

## CustomWidget

Renders a custom Blade view as a widget.

```php
use NyonCode\WireCore\Widgets\CustomWidget;
```

### Basic Usage

```php
CustomWidget::make()
    ->heading('Quick Links')
    ->view('dashboard.quick-links')
    ->viewData(['links' => $this->getLinks()])
```

### CustomWidget API

```php
->view(string $view)                 // Blade view name
->viewData(array $data)              // data passed to view
->getCustomView(): ?string
```

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

The `pollingOnlyVisible` option (default: `true`) uses `wire:poll.visible` to avoid unnecessary requests when the widget is scrolled out of view.

---

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

```blade
<div>
    <x-wire::widget-grid :widgets="$this->getVisibleWidgets()" />
</div>
```

### WithWidgets API

```php
abstract protected function getWidgets(): array      // define widgets
protected function getWidgetColumns(): int           // grid columns (default: 2)
public function getVisibleWidgets(): array            // filtered by visibility + authorization
```

### HasWidgets Interface

```php
interface HasWidgets
{
    public function getWidgets(): array;
}
```

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
->lazy(bool $lazy = true): static
->isLazy(): bool
->render(): View
->toHtml(): string
```

Inherited from traits:

```php
// HasColumnSpan
->columnSpan(int|string $span): static
->getColumnSpan(): int|string

// HasExtraAttributes
->extraAttributes(array $attrs): static
->getExtraAttributes(): array

// HasPolling
->pollingInterval(?string $interval): static
->pollingOnlyVisible(bool $only = true): static

// HasVisibility + HasAuthorization
->hidden(bool|Closure $hidden): static
->visible(bool|Closure $visible): static
->permission(?string $permission): static
->authorize(?string $ability): static
->authorizeUsing(?Closure $callback): static
->isVisible(): bool
->isAuthorized(): bool
```

## Blade Components

```blade
{{-- Widget grid --}}
<x-wire::widget-grid :widgets="$widgets" />

{{-- Individual widget views --}}
wire-core::widgets.stats-overview
wire-core::widgets.chart
wire-core::widgets.table
wire-core::widgets.custom
wire-core::widgets.widget-grid
```
