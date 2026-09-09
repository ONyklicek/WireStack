---
order: 30
summary: "Čárové, plošné a sloupcové grafy — odkud jsou data, co se kreslí v prohlížeči a z čeho je každá série složená."
---

# Grafy

Grafový widget je dotaz a tvar: data se skládají na serveru, kreslí se v
prohlížeči a hranice mezi tím rozhoduje o tom, co stojí obnovení. `BarChartWidget`
je ten, který nepotřebuje žádný JavaScript — a proto je to samostatná třída, ne
volba.

## ChartWidget

Chart widget s integrací Chart.js. Podporuje line, bar, pie a doughnut charty.

```php
use NyonCode\WireCore\Widgets\ChartWidget;
```

> **Vyžaduje Chart.js.** Widget vykreslí `<canvas>` a inicializuje ho přes Alpine. Zahrňte [Chart.js](https://www.chartjs.org/) na stránku — přes CDN nebo váš bundle — nebo canvas zůstane prázdný a zaloguje se varování v konzoli. Stylování datasetů (`borderColor`, `fill`, `tension`, …) se předává rovnou do Chart.js.
>
> Vlastní Alpine controller widgetu od vás nepotřebuje nic: dodává se jako bundle balíčku a widget si ho vyzvedne, když se vykresluje. Grafy jsou těžký, volitelný asset, takže záměrně *není* v sadě [`@wireStackScripts`](../../start/getting-started.md#javascriptove-assety) načítané vždy.

### Základní použití

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

### Typy chartů

```php
->type('line')        // line chart (výchozí)
->type('bar')         // bar chart
->type('pie')         // pie chart
->type('doughnut')    // doughnut chart
```

### Dynamická data s closurami

Datasety a labely přijímají closury. Aktivní hodnota filtru se předá jako argument:

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

### Dropdown filtr

```php
->filter(array $options, ?string $default = null)
```

Přidá dropdown na widget. Vybraný klíč se předá closurám na datasety a labely —
**na serveru**, protože tam ty closury žijí.

```php
ChartWidget::make()
    ->heading('Revenue')
    ->filter([
        'week' => 'This Week',
        'month' => 'This Month',
        'year' => 'This Year',
    ], 'month')
```

Změna výběru zavolá na hostiteli `filterWidget()`, ten closury znovu vyřeší a
odpoví jen markupem tohoto widgetu. Obal grafu nese aktivní klíč ve svém
`wire:key`, takže morph element **nahradí**, místo aby ho záplatoval — Alpine
nikdy znovu nevyhodnotí `x-data` na elementu, který už inicializoval, takže
záplatovaný atribut by nikdo nepřečetl a Chart.js by dál kreslil starou sérii.
Nahrazení starý graf zbourá přes `destroy()` v controlleru a nad novými daty
postaví nový.

> **Tohle je novinka 2.0.** Filtr se dřív řešil v prohlížeči: Alpine
> `updateChart()` přiřadil `this.labels` a `this.datasets` zpátky na graf, se
> kterým byl sestaven, takže změna výběru překreslila identický graf a closury
> nikdy neběžely s ničím jiným než se svou výchozí hodnotou.

`filter()` už není vlastnost grafu — je na základní třídě widgetu, takže seznam
nebo progress nástěnka berou stejnou mapu voleb. Viz
[Filtry](index.md#filtry).

### ChartWidget API

```php
->type(string $type)                       // 'line', 'bar', 'pie', 'doughnut'
->getType(): string
->datasets(array|Closure $datasets)        // formát datasetu Chart.js
->getDatasets(): array
->labels(array|Closure $labels)            // labely osy x
->getLabels(): array
->options(array $options)                  // Chart.js options sloučené přes výchozí typu
->getOptions(): array
```

`filter()`, `activeFilter()` a jejich gettery sdílí každý widget a jsou
zdokumentované na [základní třídě widgetu](index.md#reference-widget-api).

### Konvenční widgety

Deklarativní presety nad `ChartWidget`, takže dashboard vyjadřuje záměr místo `->type(...)`:

```php
use NyonCode\WireCore\Widgets\DoughnutChartWidget;
use NyonCode\WireCore\Widgets\LineChartWidget;
use NyonCode\WireCore\Widgets\PieChartWidget;

LineChartWidget::make()->heading('Revenue')->labels([...])->datasets([...]);
PieChartWidget::make()->heading('By Category')->labels([...])->datasets([...]);
DoughnutChartWidget::make()->heading('By Status')->labels([...])->datasets([...]);
```

`PieChartWidget` a `DoughnutChartWidget` ukazují Chart.js legendu ve výchozím stavu (pozice nahoře) — výseče koláče na ni spoléhají. Vše ostatní odpovídá `ChartWidget`.

### Chart.js options

Přepište jakoukoli Chart.js option pomocí `options()`; pole se sloučí **přes** výchozí typu (`responsive: true`, `maintainAspectRatio: false`, plus pie/doughnut legenda), takže specifikujete jen to, co se mění:

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

**Bez závislostí** bar chart vykreslený zcela Tailwind utility třídami — bez Chart.js, bez `<canvas>`, bez JavaScriptu. Použijte ho pro kompaktní, tiskově přívětivé dashboardy. Je to odlišný widget od [`ChartWidget`](#chartwidget); oba mohou žít na stejném dashboardu.

```php
use NyonCode\WireCore\Widgets\BarChartWidget;
use NyonCode\WireCore\Widgets\ChartItem;
```

Widget má tři vizuální režimy, vybrané z `type()` + `variant()`:

| `type()` | `variant()` | Vzhled |
| --- | --- | --- |
| `vertical` | `finance` | Vertikální sloupce: formátovaná hodnota nahoře, světlá max-height dráha, `MM / YYYY` popisek dole |
| `vertical` | `system` / `default` | Vertikální sloupce na 0–100% dráze s hlavičkou ikona + label + procento a volitelnými mřížkovými čárami |
| `horizontal` | `system` / `default` | Horizontální progress bary: label vlevo, hodnota vpravo |

### Finanční sloupce

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

### Systémové metriky (vertikální, s mřížkovými čárami)

```php
BarChartWidget::make()
    ->heading('Přehled systému')
    ->type('vertical')
    ->variant('system')
    ->showGrid()           // 0% / 25% / 50% / 75% / 100% vodicí čáry
    ->showMenu()           // "⋯" prvek options v hlavičce karty
    ->maxValue(100)        // procentní režim (0–100 dráha)
    ->verticalLabels()     // popisek každého sloupce otočený svisle vedle něj (vejdou se dlouhé názvy)
    ->items([
        ChartItem::make('CPU')->value(72)->formattedValue('72 %')->icon('cpu-chip')->color('blue')->percentage(72),
        ChartItem::make('RAM')->value(54)->formattedValue('54 %')->icon('circle-stack')->color('green')->percentage(54),
        ChartItem::make('Disk')->value(81)->formattedValue('81 %')->icon('server')->color('orange')->percentage(81),
        ChartItem::make('GPU')->value(36)->formattedValue('36 %')->icon('bolt')->color('purple')->percentage(36),
    ])
```

### Systémové metriky (horizontální)

Stejné položky, přepněte `type('horizontal')`:

```php
BarChartWidget::make()
    ->type('horizontal')
    ->variant('system')
    ->maxValue(100)
    ->items([ /* ChartItem… */ ])
```

### Jak se resolvuje výška výplně

Procento výplně každého sloupce (`percentageFor(ChartItem)`) se resolvuje v tomto pořadí:

1. Explicitní per-item `->percentage(0–100)` vyhrává.
2. Jinak se hodnota škáluje proti widget `->maxValue()`.
3. Jinak (procentní režim bez stropu) se hodnota auto-škáluje proti největší položce.

Výsledek se vždy ořízne na `0–100`. Velikost výplně je **jediný** dynamický styl, předaný jako CSS proměnná a konzumovaný Tailwind arbitrary hodnotami:

```html
<div class="… h-[var(--value)]" style="--value: 72%"></div>
```

### Bezpečné barvy

Hodnoty `color()` mapují přes pevný allow-list (`HasColor::getGradientFillClasses()` / `getFillTextClasses()`) — řetězce dodané vlastníkem nemohou **nikdy** injektovat libovolné třídy. Podporované chart odstíny:

| klíč | fill gradient | akcentový text |
| --- | --- | --- |
| `blue` | `from-blue-500 to-blue-600` | `text-blue-600` |
| `green` | `from-green-500 to-green-600` | `text-green-600` |
| `orange` | `from-orange-500 to-orange-600` | `text-orange-600` |
| `purple` | `from-purple-500 to-purple-600` | `text-purple-600` |
| `gray` | `from-slate-400 to-slate-500` | `text-slate-600` |

(Brand alias `primary` a širší slovník palety — `red`, `amber`, `cyan`, `pink`, … — jsou přijímány také.)

### Validace

```php
->type('diagonal');         // vyhodí InvalidArgumentException (povoleno: vertical, horizontal)
->variant('pie');           // vyhodí InvalidArgumentException (povoleno: finance, system, default)
ChartItem::make('CPU')->percentage(120);  // vyhodí InvalidArgumentException (0–100)
```

### BarChartWidget API

```php
->type(string $type)                 // 'vertical' | 'horizontal'   (validováno)
->getType(): string
->variant(string $variant)           // 'finance' | 'system' | 'default'   (validováno)
->getVariant(): string
->items(array|Closure $items)        // array<ChartItem>, nebo fn (?string $filter): array — validováno tak i tak
->getItems(): array
->showGrid(bool $show = true)        // mřížkové čáry (system vertical)
->shouldShowGrid(): bool
->showMenu(bool $show = true)        // prvek options v hlavičce karty
->shouldShowMenu(): bool
->maxValue(int|float|null $max)      // absolutní strop; null = procentní režim
->getMaxValue(): ?float
->height(int $px)                    // výška vertikálního plotu (výchozí 240)
->getHeight(): int
->verticalLabels(bool $on = true)    // popisek každého sloupce svisle vedle něj (vertikální grafy; dlouhé názvy)
->hasVerticalLabels(): bool
->rounded(string $scale)             // radius karty: 'lg' | 'xl' | '2xl' (výchozí) | '3xl' | …
->getRounded(): string
->percentageFor(ChartItem $item): float   // resolvovaná 0–100 výplň
->fillClassesFor(ChartItem $item): string // bezpečné gradient třídy
->textClassesFor(ChartItem $item): string // bezpečné akcentové text třídy
```

---

## ChartItem

Jeden sloupec v [`BarChartWidget`](#barchartwidget).

```php
use NyonCode\WireCore\Widgets\ChartItem;
```

### ChartItem API

```php
ChartItem::make(string $label)
->value(int|float $value)                 // surová numerická hodnota
->getValue(): float
->formattedValue(?string $formatted)      // zobrazovací řetězec, např. '125 000 Kč' / '72 %'
->getFormattedValue(): string             // spadne na surovou hodnotu
->color(string|Color|null $color)         // bezpečný klíč barvy (výchozí 'primary')
->getColor(): string
->percentage(int|float $percentage)       // explicitní 0–100 výplň (validováno)
->getPercentage(): ?float
->hasPercentage(): bool
->icon(string|Icon|null $icon)            // název ikony (system/horizontal varianty)
->getIcon(): ?string
->getLabel(): string
->extraAttributes(array $attrs)
```

---

## Související

- [Widgety](index.md) — polling, autorizace a sdílené API
- [Statistiky](stats.md) — číslo tam, kde by graf byl moc
- [Barvy](../foundation/colors.md) — kde se řeší barva série
- [JavaScriptové assety](../../start/getting-started.md#javascriptove-assety) — co graf položí na stránku
