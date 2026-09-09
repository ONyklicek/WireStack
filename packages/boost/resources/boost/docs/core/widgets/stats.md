---
order: 20
summary: "A row of figures with their trend — the overview widget, and the value object each card is built from."
---

# Stats

The most common panel on a dashboard is a number with a word under it and an
arrow beside it. `StatsOverviewWidget` is a row of those, and `Stat` is one card:
a label, a value, an optional description, a trend and a sparkline.

## StatsOverviewWidget

Grid of stat cards — ideal for KPIs, counters, and summary metrics.

The configured column count is the *desktop* layout: the grid always collapses
to one column on mobile and two from the `sm` breakpoint, growing to the
configured count (max 4) on large screens.

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
->color(?string $color)                   // any palette color key (e.g. 'success', 'danger', 'primary')
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

## Related

- [Widgets](index.md) — what every widget shares
- [Charts](charts.md) — when the shape matters more than the figure
- [Colors](../foundation/colors.md) — the vocabulary a stat's colour speaks
- [Dashboards](dashboards.md) — placing a stats row among other widgets
