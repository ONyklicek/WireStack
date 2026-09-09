---
order: 20
summary: "Řada čísel s trendem — přehledový widget a hodnotový objekt, ze kterého je každá karta postavená."
---

# Statistiky

Nejběžnější panel na dashboardu je číslo se slovem pod ním a šipkou vedle.
`StatsOverviewWidget` je řada takových a `Stat` je jedna karta: popisek, hodnota,
volitelný popis, trend a sparkline.

## StatsOverviewWidget

Grid stat karet — ideální pro KPI, počítadla a souhrnné metriky.

Nakonfigurovaný počet sloupců je *desktop* layout: grid se vždy sbalí
na jeden sloupec na mobilu a dva od breakpointu `sm`, rostoucí na
nakonfigurovaný počet (max 4) na velkých obrazovkách.

```php
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Stat;
```

### Základní použití

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

### Sloupce gridu

```php
->columns(int $columns)   // 1-4 sloupce (oříznuto)
```

Výchozí jsou 3 sloupce. Grid je responzivní.

### StatsOverviewWidget API

```php
->stats(array $stats)               // pole instancí Stat
->getStats(): array
->columns(int $columns)             // sloupce gridu (1-4)
->getGridColumns(): int
```

---

## Stat

Jednotlivá stat karta uvnitř `StatsOverviewWidget`.

```php
use NyonCode\WireCore\Widgets\Stat;
```

### Kompletní příklad

```php
Stat::make('Monthly Revenue', '$12,430')
    ->description('8% increase from last month')
    ->descriptionIcon('arrow-up')
    ->color('success')
    ->icon('currency-dollar')
    ->chart([7, 3, 4, 5, 6, 3, 5, 8])
    ->extraAttributes(['class' => 'ring-2 ring-green-200'])
```

### Sparkline chart

```php
->chart(array $data)   // pole numerických datových bodů pro SVG sparkline
```

```php
Stat::make('Active Users', '2,847')
    ->chart([12, 15, 18, 14, 22, 25, 28, 32])
    ->color('primary')
```

### Stat API

```php
Stat::make(string $label, string $value)
->description(?string $description)       // sekundární text
->descriptionIcon(?string $icon)          // ikona vedle popisu
->color(?string $color)                   // libovolný klíč barvy palety (např. 'success', 'danger', 'primary')
->icon(?string $icon)                     // ikona stat karty
->chart(array $data)                      // sparkline datové body (int|float)
->extraAttributes(array $attrs)           // vlastní HTML atributy
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

## Související

- [Widgety](index.md) — co mají všechny widgety společné
- [Grafy](charts.md) — když na tvaru záleží víc než na čísle
- [Barvy](../foundation/colors.md) — slovník, kterým mluví barva statistiky
- [Dashboardy](dashboards.md) — umístění řady statistik mezi ostatní widgety
