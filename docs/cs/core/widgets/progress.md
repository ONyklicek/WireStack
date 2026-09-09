---
order: 32
summary: "Řádky výplně proti dráze — jak daleko je číslo k číslu, kterého má dosáhnout, a položka, ze které je každý řádek složený."
---

# ProgressWidget

Panel pro číslo, které někam **míří**: prodejní kvóta, utracený rozpočet,
zaplňující se disk, dohořívající sprint. Statistická karta říká číslo a sloupcový
graf porovnává čísla mezi sebou; ani jedno neřekne, jak daleko po cestě to číslo
je — a přesně na to výplň proti dráze odpoví jedním pohledem.

```php
use NyonCode\WireCore\Widgets\ProgressWidget;
```

## Jak to funguje

**Čisté CSS, vyřešené na serveru.** Stejně jako `BarChartWidget` a na rozdíl od
`ChartWidget` tu není žádný Chart.js, žádné plátno a není na co čekat. Geometrii
každého řádku počítá `ProgressItem::getPercentage()` v PHP — aritmetika v šabloně
je aritmetika, kterou nic neotestuje.

**Zlomek a jeho tři okraje.** `value / target × 100`, oříznuté na 0–100 z obou
stran:

| Hodnota | Vykreslí se jako | Proč |
| --- | --- | --- |
| `value(300)->target(120)` | plná dráha | Výplň je šířka v pevné krabici; překročený cíl nesmí nakreslit pruh širší než jeho dráha |
| `value(-40)->target(120)` | prázdná dráha | Ne pruh rostoucí doleva |
| `target(0)` | prázdná dráha | „0 z 0“ je mnohem častěji nenakonfigurovaný řádek než hotový, a plný pruh by ohlásil úspěch, kterého nikdo nedosáhl |

**Co každý řádek vypíše.** Zaokrouhlená procenta, pokud `formattedValue()`
neřekne jinak. Záměrně *ne* `number_format($value)`: oddělovač tisíců a
desetinná značka jsou rozhodnutí o locale a widget je špatné místo, kde ho dělat
za volajícího. Kdo chce `1.2M / 2M`, napíše přesně to.

**Odkud se bere série.** `items()` bere pole nebo closure. Closure dostane
aktivní klíč filtru widgetu, běží při každém renderu a nikdy se nememoizuje —
takže filtr na tomto widgetu data opravdu znovu vyřeší. Viz
[Filtry](index.md#filtry).

**Co to stojí.** Jeden render view na celý widget, ať je řádků kolik chce. Žádný
render na řádek neexistuje: řádky jsou `@foreach` uvnitř vlastní šablony widgetu.

**Past.** Šířka výplně je inline `style`, ne třída Tailwindu. Procento je spojitá
hodnota a utility třída je pevná množina, takže `w-[73.4%]` by vyžadovalo, aby
JIT přesně to číslo viděl při buildu. Číslo se vyřeší a ořízne v PHP a nikdy se
nedostane do názvu třídy, takže nic, co volající dodá, nemůže žádnou vsunout.

## Základní použití

```php
ProgressWidget::make()
    ->heading('Quarterly targets')
    ->items([
        ProgressItem::make('New MRR')->value(84_000)->target(120_000)->color('success'),
        ProgressItem::make('Churn budget')->value(31)->target(40)->color('warning'),
    ])
```

## Hodnoty na řádcích

Každý řádek vypíše svůj údaj vedle popisku. Na husté nástěnce, kde je porovnáním
sama výplň, ho vypněte a nechte mluvit pruhy:

```php
ProgressWidget::make()
    ->items($items)
    ->showValues(false)
```

## Formátování údaje

```php
ProgressItem::make('Storage')
    ->value(412)
    ->target(1_000)
    ->formattedValue('412 GB / 1 TB')   // vypíše se místo „41 %“
```

## Prázdný stav

Widget, jehož dotaz se vrátil bez záznamů, ukáže větu, ne prázdnou kartu:

```php
ProgressWidget::make()
    ->items(fn () => $this->quotas())
    ->emptyState('No targets set for this quarter.')
```

Předání `null` vrátí výchozí text (`Není co zobrazit.`, přeložený).

## Přístupnost

Každá dráha je `role="progressbar"` a nese `aria-valuenow`, `aria-valuemin` a
`aria-valuemax`, popsaná popiskem řádku. Vypsaná hodnota je označená
`aria-hidden`, protože stejný údaj už na pruhu je — bez toho by odečítač
obrazovky každý řádek přečetl dvakrát.

## Rozšířený příklad

```php
namespace App\Dashboards;

use App\Models\Deal;
use NyonCode\WireCore\Widgets\Dashboard;
use NyonCode\WireCore\Widgets\ProgressItem;
use NyonCode\WireCore\Widgets\ProgressWidget;

class SalesDashboard extends Dashboard
{
    public function widgets(): array
    {
        return [
            ProgressWidget::make()                                        // [tl! focus:start]
                ->heading('Quota attainment')
                ->description('Closed-won against target, per rep')
                ->columnSpan('full')
                ->filter(['quarter' => 'This quarter', 'year' => 'This year'])
                ->items(fn (?string $range) => Deal::quotaProgress($range)
                    ->map(fn (array $row) => ProgressItem::make($row['rep'])
                        ->value($row['closed'])
                        ->target($row['quota'])
                        ->formattedValue($row['closed_formatted'].' / '.$row['quota_formatted'])
                        ->description($row['deals'].' deals')
                        ->color($row['closed'] >= $row['quota'] ? 'success' : 'primary'))
                    ->all())
                ->emptyState('No quotas configured.'),                    // [tl! focus:end]
        ];
    }
}
```

## ProgressWidget API

```php
->showValues(bool $condition = true)   // vypsat údaj vedle popisku každého řádku — výchozí true
->showsValues(): bool
```

Vše ostatní, co progress widget bere, je sdílené a zdokumentované centrálně:
`items()`, `emptyState()`, `filter()`, `lazy()`, `heading()`, `columnSpan()`,
`pollingInterval()` a settery autorizace jsou na
[základní třídě widgetu](index.md#reference-widget-api).

---

## ProgressItem

Jeden řádek: popisek, údaj a údaj, ke kterému míří.

```php
use NyonCode\WireCore\Widgets\ProgressItem;
```

### Úplný příklad

```php
ProgressItem::make('New MRR')
    ->value(84_000)
    ->target(120_000)
    ->formattedValue('84K / 120K')
    ->description('vs. 71K last quarter')
    ->icon('outline:arrow-trending-up')
    ->color('success')
    ->extraAttributes(['data-testid' => 'mrr'])
```

### ProgressItem API

Vytváří se přes `ProgressItem::make(string $label)`.

```php
->value(int|float $value)                 // aktuální údaj — výchozí 0
->target(int|float $target)               // kam míří — výchozí 100
->description(?string $description)       // druhý řádek pod popiskem
->formattedValue(?string $value)          // vypíše se místo procent
->icon(string|Icon|null $icon)            // ikona vedle popisku
->color(?string $color)                   // libovolný klíč palety ('success', 'danger', …)
->extraAttributes(array $attrs)           // vlastní HTML atributy na řádku
->getLabel(): string
->getValue(): float
->getTarget(): float
->getPercentage(): float                  // 0–100, oříznuté
->isComplete(): bool                      // údaj dosáhl svého cíle
->getFormattedValue(): string
->getDescription(): ?string
->getIcon(): ?string
->getColor(): ?string
```

## Související

- [Statistiky](stats.md) — číslo s trendem, když není žádný cíl, kterého se má dosáhnout
- [Grafy](charts.md) — `BarChartWidget`, když je porovnání mezi čísly navzájem, ne proti cíli
- [Seznamy](list.md) — druhý čistě CSS widget, pro položky místo čísel
- [Widgety](index.md) — sdílený základ: polling, filtry, odklad, autorizace
