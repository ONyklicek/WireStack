---
order: 30
summary: "Line, area and bar charts — where the data comes from, what is drawn in the browser, and the item each series is made of."
---

# Charts

A chart widget is a query and a shape: the data is assembled on the server, the
drawing happens in the browser, and the boundary between the two decides what a
refresh costs. `BarChartWidget` is the one that needs no JavaScript at all, which
is why it is a separate class rather than an option.

## ChartWidget

Chart widget with Chart.js integration. Supports line, bar, pie, and doughnut charts.

```php
use NyonCode\WireCore\Widgets\ChartWidget;
```

> **Requires Chart.js.** The widget renders a `<canvas>` and initializes it through Alpine. Include [Chart.js](https://www.chartjs.org/) on the page — via CDN or your bundle — or the canvas stays empty and a console warning is logged. Dataset styling (`borderColor`, `fill`, `tension`, …) is passed straight through to Chart.js.
>
> The widget's own Alpine controller needs nothing from you: it ships as a package bundle and the widget fetches it when it renders. Charts are a heavy, optional asset, so it is deliberately *not* in the always-loaded [`@wireStackScripts`](../../start/getting-started.md#javascript-assets) set.

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

Adds a dropdown on the widget. The selected key is passed to the dataset and
label closures — **on the server**, which is where those closures live.

```php
ChartWidget::make()
    ->heading('Revenue')
    ->filter([
        'week' => 'This Week',
        'month' => 'This Month',
        'year' => 'This Year',
    ], 'month')
```

Changing the selection calls `filterWidget()` on the host, which re-resolves the
closures and answers with this widget's markup alone. The chart's wrapper carries
the active key in its `wire:key`, so the morph *replaces* the element rather than
patching it — Alpine never re-evaluates `x-data` on an element it has already
initialised, so a patched attribute would be read by nobody and Chart.js would
keep drawing the old series. The replacement tears the old chart down through the
controller's `destroy()` and builds a new one over the new data.

> **This is new in 2.0.** The filter used to be resolved in the browser: an
> Alpine `updateChart()` assigned `this.labels` and `this.datasets` back onto the
> chart it was constructed with, so changing the selection redrew the identical
> chart and the closures never ran with anything but their default.

`filter()` is no longer a chart feature — it is on the widget base, so a list or
a progress board takes the same options map. See
[Filters](index.md#filters).

### ChartWidget API

```php
->type(string $type)                       // 'line', 'bar', 'pie', 'doughnut'
->getType(): string
->datasets(array|Closure $datasets)        // Chart.js dataset format
->getDatasets(): array
->labels(array|Closure $labels)            // x-axis labels
->getLabels(): array
->options(array $options)                  // Chart.js options merged over the type defaults
->getOptions(): array
```

`filter()`, `activeFilter()` and their getters are shared by every widget and
documented on the [widget base](index.md#widget-api-reference).

### Convenience Widgets

Declarative presets over `ChartWidget`, so a dashboard states intent instead of `->type(...)`:

```php
use NyonCode\WireCore\Widgets\DoughnutChartWidget;
use NyonCode\WireCore\Widgets\LineChartWidget;
use NyonCode\WireCore\Widgets\PieChartWidget;

LineChartWidget::make()->heading('Revenue')->labels([...])->datasets([...]);
PieChartWidget::make()->heading('By Category')->labels([...])->datasets([...]);
DoughnutChartWidget::make()->heading('By Status')->labels([...])->datasets([...]);
```

`PieChartWidget` and `DoughnutChartWidget` show the Chart.js legend by default (top position) — pie slices rely on it. Everything else matches `ChartWidget`.

### Chart.js Options

Override any Chart.js option with `options()`; the array is merged **over** the type's defaults (`responsive: true`, `maintainAspectRatio: false`, plus the pie/doughnut legend), so you only specify what changes:

```php
LineChartWidget::make()
    ->datasets([...])
    ->options([
        'scales' => ['y' => ['beginAtZero' => true]],
        'plugins' => ['legend' => ['display' => false]],
    ])
```

---

## BarChartWidget

A **dependency-free** bar chart rendered entirely with Tailwind utility classes — no Chart.js, no `<canvas>`, no JavaScript. Use it for compact, print-friendly dashboards. It is a distinct widget from [`ChartWidget`](#chartwidget); both can live on the same dashboard.

```php
use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;
```

The widget has three visual modes, picked from `type()` + `variant()`:

| `type()` | `variant()` | Look |
| --- | --- | --- |
| `vertical` | `finance` | Vertical bars: formatted value above, light max-height track, `MM / YYYY` caption below |
| `vertical` | `system` / `default` | Vertical bars on a 0–100% track with an icon + label + percentage header and optional grid lines |
| `horizontal` | `system` / `default` | Horizontal progress bars: label on the left, value on the right |

### Finance bars

```php
BarChartWidget::make()
    ->heading('Přehled tržeb')
    ->type('vertical')
    ->variant('finance')
    ->items([
        ChartItem::make('01 / 2024')->value(125000)->formattedValue('125 000 Kč')->color('blue')->percentage(70),
        ChartItem::make('02 / 2024')->value(98500)->formattedValue('98 500 Kč')->color('green')->percentage(55),
    ])
```

### System metrics (vertical, with grid lines)

```php
BarChartWidget::make()
    ->heading('Přehled systému')
    ->type('vertical')
    ->variant('system')
    ->showGrid()           // 0% / 25% / 50% / 75% / 100% guide lines
    ->showMenu()           // a "⋯" options affordance in the card header
    ->maxValue(100)        // percentage mode (0–100 track)
    ->verticalLabels()     // rotate each bar's label vertically beside it (fits long names)
    ->items([
        ChartItem::make('CPU')->value(72)->formattedValue('72 %')->icon('cpu-chip')->color('blue')->percentage(72),
        ChartItem::make('RAM')->value(54)->formattedValue('54 %')->icon('circle-stack')->color('green')->percentage(54),
        ChartItem::make('Disk')->value(81)->formattedValue('81 %')->icon('server')->color('orange')->percentage(81),
        ChartItem::make('GPU')->value(36)->formattedValue('36 %')->icon('bolt')->color('purple')->percentage(36),
    ])
```

### System metrics (horizontal)

Same items, switch `type('horizontal')`:

```php
BarChartWidget::make()
    ->type('horizontal')
    ->variant('system')
    ->maxValue(100)
    ->items([ /* ChartItem… */ ])
```

### How fill height is resolved

Each bar's fill percentage (`percentageFor(ChartItem)`) is resolved in this order:

1. An explicit per-item `->percentage(0–100)` wins.
2. Otherwise the value is scaled against the widget `->maxValue()`.
3. Otherwise (percentage mode with no ceiling) the value is auto-scaled against the largest item.

The result is always clamped to `0–100`. The fill size is the **only** dynamic style, passed as a CSS variable and consumed by Tailwind arbitrary values:

```html
<div class="… h-[var(--value)]" style="--value: 72%"></div>
```

### Safe colors

`color()` values map through a fixed allow-list (`HasColor::getGradientFillClasses()` / `getFillTextClasses()`) — owner-supplied strings can **never** inject arbitrary classes. Supported chart hues:

| key | fill gradient | accent text |
| --- | --- | --- |
| `blue` | `from-blue-500 to-blue-600` | `text-blue-600` |
| `green` | `from-green-500 to-green-600` | `text-green-600` |
| `orange` | `from-orange-500 to-orange-600` | `text-orange-600` |
| `purple` | `from-purple-500 to-purple-600` | `text-purple-600` |
| `gray` | `from-slate-400 to-slate-500` | `text-slate-600` |

(The brand `primary` alias and the wider palette vocabulary — `red`, `amber`, `cyan`, `pink`, … — are accepted too.)

### Validation

```php
->type('diagonal');         // throws InvalidArgumentException (allowed: vertical, horizontal)
->variant('pie');           // throws InvalidArgumentException (allowed: finance, system, default)
ChartItem::make('CPU')->percentage(120);  // throws InvalidArgumentException (0–100)
```

### BarChartWidget API

```php
->type(string $type)                 // 'vertical' | 'horizontal'   (validated)
->getType(): string
->variant(string $variant)           // 'finance' | 'system' | 'default'   (validated)
->getVariant(): string
->items(array|Closure $items)        // array<ChartItem>, or fn (?string $filter): array — validated either way
->getItems(): array
->showGrid(bool $show = true)        // grid lines (system vertical)
->shouldShowGrid(): bool
->showMenu(bool $show = true)        // card-header options affordance
->shouldShowMenu(): bool
->maxValue(int|float|null $max)      // absolute ceiling; null = percentage mode
->getMaxValue(): ?float
->height(int $px)                    // vertical plot height (default 240)
->getHeight(): int
->verticalLabels(bool $on = true)    // rotate each bar's label vertically beside it (vertical charts; fits long names)
->hasVerticalLabels(): bool
->rounded(string $scale)             // card radius: 'lg' | 'xl' | '2xl' (default) | '3xl' | …
->getRounded(): string
->percentageFor(ChartItem $item): float   // resolved 0–100 fill
->fillClassesFor(ChartItem $item): string // safe gradient classes
->textClassesFor(ChartItem $item): string // safe accent text classes
```

---

## ChartItem

A single bar in a [`BarChartWidget`](#barchartwidget).

```php
use NyonCode\WireCore\Widgets\ChartItem;
```

### ChartItem API

```php
ChartItem::make(string $label)
->value(int|float $value)                 // raw numeric value
->getValue(): float
->formattedValue(?string $formatted)      // display string, e.g. '125 000 Kč' / '72 %'
->getFormattedValue(): string             // falls back to the raw value
->color(string|Color|null $color)         // safe color key (default 'primary')
->getColor(): string
->percentage(int|float $percentage)       // explicit 0–100 fill (validated)
->getPercentage(): ?float
->hasPercentage(): bool
->icon(string|Icon|null $icon)            // icon name (system/horizontal variants)
->getIcon(): ?string
->getLabel(): string
->extraAttributes(array $attrs)
```

---

## Related

- [Widgets](index.md) — polling, authorization and the shared API
- [Stats](stats.md) — a figure where a chart would be too much
- [Colors](../foundation/colors.md) — where a series' colour is resolved
- [JavaScript Assets](../../start/getting-started.md#javascript-assets) — what a chart puts on the page
