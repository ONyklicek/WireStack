---
order: 23
nav: false
---

# MetricColumn

Číslo čtené jako měření: hodnota a volitelně trend, který za ní stojí.

```php
use NyonCode\WireTable\Columns\MetricColumn;
```

## Jak to funguje

Nic neagreguje. Tečková notace už převádí cestu přes relaci na `withCount` /
`withSum` poddotaz, takže `MetricColumn::make('orders.count')` je pořád ten jeden
dotaz, co byl vždycky — viz [Cesty přes relace](relations.md#agregaty). Co ten typ
přidává, je prezentace:

- výchozí hodnoty pro čísla, sdílené s [MoneyColumn](money.md) — zarovnání
  doprava, tabulární číslice, nezalamování — takže čísla lícují po sloupci dolů
  a stacked mobilní karta si metriku vezme jako svoje hlavní číslo;
- volitelný **trend** vykreslený vedle čísla jako jedna SVG polyline.

## Základní použití

```php
MetricColumn::make('orders.count')->label('Objednávky')  // withCount, doprava
MetricColumn::make('items.sum.amount')->label('Objem')   // withSum
MetricColumn::make('open_tickets')                       // obyčejný sloupec taky funguje
```

## Přidání trendu

```php
MetricColumn::make('orders.count')
    ->label('Objednávky')
    ->trend(fn (Customer $record): array => $record->orders_per_month)
```

Řada je obyčejné pole čísel v pořadí čtení, od nejstaršího. Kreslí ji stejná
geometrie, jakou používá stats widget, takže buňka tabulky a dlaždice na
dashboardu ukazují pro stejná data stejný tvar.

### Řadu dodáváte vy, a to schválně

Řadu per záznam nejde odvodit z cesty toho sloupce: *objednávky po měsících pro
tohohle zákazníka* je druhý tvar dotazu, ne formátování toho prvního. Closure
drží to rozhodnutí — i případné N+1 — tam, kde je vidět.

```php
// Správně: řada přijde se stránkou a closure ji jen čte.
public function table(Table $table): Table
{
    return $table
        ->model(Customer::class)
        ->query(fn ($query) => $query->withCount('orders')->with('monthlyTotals'))  // [tl! focus]
        ->columns([
            TextColumn::make('name')->sortable(),
            MetricColumn::make('orders_count')                                      // [tl! focus]
                ->label('Objednávky')                                               // [tl! focus]
                ->trend(fn (Customer $r): array => $r->monthlyTotals                // [tl! focus]
                    ->pluck('total')->all()),                                       // [tl! focus]
        ]);
}

// Špatně: tohle se zeptá jednou za řádek, přesně jak to je napsané.
->trend(fn (Customer $r): array => $r->orders()->sum('total') ? [] : [])
```

## Jak musí řada vypadat

| Řada | Vykreslí se jako |
|------|------------------|
| `[1, 5, 3]` | křivka |
| `[4, 4, 4]` | rovná čára **středem** — stabilní, ne spadlé na nulu |
| `[7]` | rovná čára; jedno čtení není trend, ale není to ani nic |
| `[4, null, 4]` | ta dvě čtení, která byla — viz níže |
| `[]` | vůbec nic: buňka je obyčejné číslo |

Nečíselná čtení se **zahazují, nepřevádějí**. `null` měsíc znamená „žádné
čtení" a vykreslit ho jako `0` si vymyslí propad na dno a zpátky, který se nikdy
nestal.

## Barva trendu

Tah se řídí barvou sloupce, nebo tou, která je řečená jen pro trend:

```php
MetricColumn::make('churn')->color('danger')                   // číslo i čára
MetricColumn::make('orders.count')->trend($series, 'success')  // jen čára
```

## Přepsání výchozích hodnot

```php
MetricColumn::make('orders.count')
    ->alignment('left')     // …a přestává být metrikou stacked karty
    ->textSize('lg')
```

## MetricColumn API

```php
->trend(Closure $callback, ?string $color = null)   // fn (Model $record): array<int, int|float>
->hasTrend(): bool
->getTrend(Model $record): array
->getTrendColorClass(): string
```

Všechno z [TextColumn](text.md) — formátování, limity, prefixy, souhrny — platí
beze změny.
