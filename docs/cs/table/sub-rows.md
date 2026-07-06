---
order: 60
---

# Podřádky

Podřádky vykreslují související dětské záznamy v rozbalitelném panelu pod každým
rodičovským řádkem. Použijte je, když uživatelé potřebují proniknout do detailu —
položky faktury, zásilky objednávky, úkoly projektu — bez opuštění tabulky.

```text
┌───┬────────────┬──────────────┬───────────┬──────────────┐
│ ▾ │ INV-1001   │ Northwind    │   paid    │   9 350 Kč   │  ← rodičovský řádek
│   └──────────────────────────────────────────────────────┐
│       Product        Qty   Unit        Line total  Actions│  ← dětská tabulka
│       27" monitor      1   5 600 Kč      5 600 Kč  [✎][🗑] │
│       Keyboard         2   1 200 Kč      2 400 Kč  [✎][🗑] │
│       Wireless mouse   3     450 Kč      1 350 Kč  [✎][🗑] │
│       Subtotal:                          9 350 Kč          │  ← součet per rodič
│   └──────────────────────────────────────────────────────┘
│ ▸ │ INV-1002   │ Globex       │  pending  │  18 100 Kč   │
└───┴────────────┴──────────────┴───────────┴──────────────┘
```

## Základní nastavení

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->label('Invoice')->sortable(),
            TextColumn::make('customer')->label('Customer'),
            TextColumn::make('total')->money(),
        ])
        ->subRows('items')                      // metoda Eloquent relace
        ->subRowColumns([                       // sloupce pro dětskou tabulku
            TextColumn::make('product')->label('Product'),
            TextColumn::make('quantity')->numeric()->label('Qty'),
            TextColumn::make('unit_price')->money()->label('Unit'),
        ]);
}
```

`subRows('items')` očekává metodu relace (`items()`) na rodičovském modelu.
Dětské sloupce jsou nezávislé na rodičovských — mohou to být úplně jiná pole.

## Kdy je použít

Podřádky použijte, když:

- rodičovský záznam vlastní **malou** sadu dětských záznamů,
- uživatelé potřebují rychlý drill-down bez změn routy,
- dětská data sdílejí stejný rozhodovací kontext jako rodičovský řádek.

Vyhněte se jim, když je dětská sada dost velká, aby si zasloužila vlastní tabulku
s vlastními filtry a stránkováním — podřádky jsou detailní prostředek, ne druhý grid.

## Rozbalení a sbalení

```php
->subRowsExpandable()                  // uživatel může přepnout (výchozí true)
->subRowsDefaultExpanded()             // začít rozbalené
->subRowsExpandable(false)             // vždy otevřené, bez přepínače
->subRowsToggleLabel('Show items')     // popisek sloupce přepínače
```

Rozbalené řádky se sledují v Livewire stavu, takže uživatel otevře jen záznamy,
které ho zajímají, a stav přežije re-render. Toolbar také vystavuje ovládání
**Rozbalit vše** / **Sbalit vše**.

## Řaditelné dětské řádky

Nechte uživatele řadit dětskou tabulku kliknutím na hlavičky sloupců, s volitelným
výchozím řazením aplikovaným před jakoukoli interakcí:

```php
->subRowsSortable(default: 'line_total', direction: 'desc')
```

```text
Product ↕   Qty ↕   Unit ↕   Line total ▼     ← ▼ aktivní řazení, ↕ řaditelné
─────────────────────────────────────────
27" monitor   1   5 600 Kč     5 600 Kč
Keyboard      2   1 200 Kč     2 400 Kč
Wireless ...  3     450 Kč     1 350 Kč
```

Řadit lze jen sloupce přítomné v `subRowColumns()` — libovolné názvy sloupců jsou
odmítnuty, takže je řazení bezpečné řídit z requestu. Kliknutí na aktivní sloupec
otočí směr; kliknutí na jiný ho seřadí vzestupně. Aktivní řazení je sdílené napříč
všemi rozbalenými rodiči.

## Limit a „Zobrazit více"

```php
->subRowsLimit(5)
```

Když je nastaven limit a existuje více dětí, na konci dětské tabulky se vykreslí
tlačítko **„Zobrazit N dalších"**. Kliknutí odhalí celou sadu pro daného rodiče
(sledováno per rodič ve stavu), zatímco počet zůstává přesný.

Eager load načte jen `limit` řádků na rodiče (nativní per-parent eager-load limit)
plus jeden count dotaz pro přesné součty — celé dětské sady se nikdy nenačítají do
paměti, dokud není rodič rozbalen přes „Zobrazit více":

```text
Product        Qty   Line total
───────────────────────────────
Keyboard         2     2 400 Kč
Wireless mouse   3     1 350 Kč
       Show 1 more                 ← objeví se, protože limit (2) < total (3)
```

## Filtrování dětského dotazu

Tvarujte podkladový dotaz relace pomocí `subRowQuery()`:

```php
use Illuminate\Database\Eloquent\Builder;

->subRowQuery(fn (Builder $query) => $query
    ->where('active', true)
    ->orderBy('sort_order')
)
```

Zapněte per-dítě interaktivní filtry pomocí `subRowsFilterable()`. Lišta filtrů
se vykreslí nad dětskou tabulkou pro každý řaditelný/filtrovatelný sloupec podřádku:

```php
->subRowsFilterable()
```

```text
Filter:  [ Product…    ]  [ price from – to ]   ✕ Reset
───────────────────────────────────────────────────────
Product        Qty   Line total
Keyboard         2     2 400 Kč     ← jen řádky odpovídající filtru
```

## Filtrování podle hodnot podřádků z filtrů tabulky

Označte jakýkoli filtr hlavní tabulky pomocí `subRows()` a míří na dětské záznamy
místo rodičovských sloupců — např. filtr Měsíc/Rok nad datem dětí. Název sloupce
filtru (zde `billed_at`) odkazuje na **dětský** model:

```php
use NyonCode\WireTable\Filters\DateFilter;

->filters([
    DateFilter::make('billed_at')->month()->subRows(),
])
```

Jeden filtr omezí vše konzistentně:

- rodiče se zmenší na ty s aspoň jedním odpovídajícím dítětem,
- rozbalené panely ukážou jen odpovídající děti,
- mezisoučty per rodič, počty „zobrazit více", rollup sloupce (`->sums()`,
  `->counts()`, …) a jejich celkové součty v patičce agregují jen odpovídající děti.

```text
Měsíc: [ 2026-06 ▾ ]
┌───┬────────────┬──────────────┐
│ ▾ │ INV-1001   │   5 000 Kč   │   ← rollup počítá jen červnové položky
│   └── June item A … June item B ──┘
│ ▾ │ INV-1003   │   5 000 Kč   │   ← INV-1002 (jen květen) zmizela
└───┴────────────┴──────────────┘
│ Celkem:        │  10 000 Kč   │   ← patička sčítá filtrované podřádky
```

<a id="building-the-diagram"></a>
### Sestavení diagramu

Žádné extra zapojení není potřeba — agregáty stačí deklarovat a filtr je zúží
automaticky. Součty lze získat dvěma způsoby podle toho, zda má být částka per
faktura viditelným rodičovským sloupcem:

**Částka jen v podřádcích.** Dejte sloupci podřádku mezisoučet per rodič
(`scope: 'subRows'`) a souhrn ve výchozím rozsahu pro celkový součet — ten se
vykreslí v hlavní patičce, spočtený v SQL nad přesně těmi dětmi, které filtr
povoluje (viz
[Celkové součty ze sloupců podřádků](summaries.md#grand-totals-from-sub-row-columns)):

```php
$table
    ->subRows('items')
    ->subRowColumns([
        TextColumn::make('product'),
        TextColumn::make('line_total')
            ->suffix(' Kč')
            ->summaryDecimals(0)
            ->summarizeSum('Subtotal', scope: 'subRows')  // patička panelu per faktura
            ->summarizeSum('Celkem'),                     // hlavní patička, filtrované děti
    ])
    ->filters([
        DateFilter::make('billed_at')->month()->subRows(),
    ]);
```

**Částka jako rodičovský sloupec.** Použijte [rollup sloupec](summaries.md#rollup-columns)
nad **stejnou relací** jako `subRows()` — shoda relace je to, co filtru umožňuje
ji omezit. Buňka pak ukazuje součet *odpovídajících* položek každé faktury (sloupec
`5 000 Kč` v diagramu) a její souhrn je celkový součet v patičce. Uložený rodičovský
atribut jako `$invoice->total` by na filtr **nereagoval** — přesně to rollup nahrazuje:

```php
$table
    ->columns([
        TextColumn::make('number')->label('Invoice'),
        TextColumn::make('items_total')
            ->label('Total')
            ->sums('items', 'line_total')   // stejná relace jako ->subRows('items')
            ->suffix(' Kč')
            ->summaryDecimals(0)
            ->summarizeSum('Celkem'),
    ])
    ->subRows('items')
    ->filters([
        DateFilter::make('billed_at')->month()->subRows(),
    ]);
```

Omezení rollupu je klíčované názvem relace: rollup nad *jinou* relací (řekněme
`->counts('payments')`) zůstane `items`-scopnutým filtrem nedotčen a dál agreguje
všechny své děti.

Viz [Filtry — Filtrování podle hodnot podřádků](filters/relationships.md#filtering-by-sub-row-values).

## Řádkové akce

Vykreslete per-dítě akce v koncové buňce akcí. Každá akce se vykreslí proti
**dětskému** záznamu, přesně jako akce hlavní tabulky proti rodiči:

```php
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;

->subRowActions([
    Action::make('edit')->label('Edit')->icon('pencil')->color('primary'),
    DeleteAction::make(),
])
```

```text
Product        Qty   Line total       Actions
─────────────────────────────────────────────
27" monitor      1     5 600 Kč     [✎ Edit][🗑 Delete]
Keyboard         2     2 400 Kč     [✎ Edit][🗑 Delete]
```

## Mezisoučty per rodič

Dejte sloupci podřádku souhrn v rozsahu `subRows` a dětská tabulka získá patičku
s tou agregací pro děti rodiče:

```php
->subRowColumns([
    TextColumn::make('product')->label('Product'),
    TextColumn::make('quantity')->numeric()->summarizeSum(scope: 'subRows'),
    TextColumn::make('line_total')
        ->numeric(0)
        ->suffix(' Kč')
        ->summaryDecimals(0)
        ->summarizeSum('Subtotal', scope: 'subRows'),
])
```

```text
Product        Qty   Line total
───────────────────────────────
27" monitor      1     5 600 Kč
Keyboard         2     2 400 Kč
Wireless mouse   3     1 350 Kč
Subtotal:        6     9 350 Kč     ← patička per rodič
```

Platí zde všechny typy agregací a formátování čísel ze stránky [Souhrny](summaries.md).
Pro součet **napříč** všemi rodiči v hlavní patičce přidejte druhý souhrn ve
výchozím rozsahu na stejný sloupec podřádku — `->summarizeSum('Celkem')` — nebo
dejte rodičovskému rollup sloupci vlastní souhrn.
Viz [Celkové součty ze sloupců podřádků](summaries.md#grand-totals-from-sub-row-columns)
a [Celkové součty přes všechny děti](summaries.md#grand-totals-across-all-children).

## Flatten režim

Flatten režim otevře podřádky **každého** rodiče najednou, místo aby nechal
uživatele rozbalovat je po jednom — hodí se pro revizi a skenování, kde chcete mít
všechen detail viditelný pohromadě:

```php
->flattenSubRows()
```

```text
┌───┬────────────┬──────────────┐
│ ▾ │ INV-1001   │   9 350 Kč   │   každá faktura je rozbalená,
│   └── Monitor … Keyboard … ───┘   ne jen ta, na kterou uživatel klikl
│ ▾ │ INV-1002   │  18 100 Kč   │
│   └── Desk … Chair … ─────────┘
│ ▾ │ INV-1003   │   8 450 Kč   │
│   └── License … Support … ────┘
└───┴────────────┴──────────────┘
```

Runtime tlačítka toolbaru **Rozbalit vše** / **Sbalit vše** přepínají stejný stav,
takže uživatelé mohou na požádání přepínat mezi flatten a per-řádkovým drill-down.

## Detail-řádkový režim (bez relace)

Vynechte `subRows()` úplně a poskytněte jen dětský pohled: rozbalený panel pak
vykreslí **samotný rodičovský záznam** — ideální pro detailní kartu. Podřádky se
aktivují, jakmile je nastaven `subRowView()` (nebo `subRowColumns()`), i bez relace:

```php
->subRowView('components.users.detail')   // žádné volání subRows() → detail-řádkový režim
```

```text
┌───┬────────────┬──────────┐
│ ▾ │ Alice      │  Active  │
│   └──────────────────────────────────┐
│       Email:    alice@example.com     │  ← váš vlastní Blade
│       Phone:    +420 777 123 456      │
│   └──────────────────────────────────┘
└───┴────────────┴──────────┘
```

## Vlastní pohled

Nahraďte výchozí renderer dětí úplně, když data nejsou přirozeně tabulková:

```php
->subRowView('components.orders.sub-rows')
```

Pohled dostane `$table`, `$component`, `$record` (rodič), `$subRows`
(kolekce dětí) a layoutové proměnné.

## Výkon: Eager loading

Podřádky se načítají pro celou stránku v **jednom dotazu** místo jednoho dotazu na
rozbaleného rodiče:

- **Flatten režim** — děti každého rodiče se načtou najednou.
- **Normální režim** — načtou se jen aktuálně rozbalení rodiče.

To odstraňuje N+1, které by jinak rostlo s počtem otevřených řádků. Čtení dětí
rodiče (a jeho počtu pro mezisoučet) pak nestojí žádné extra dotazy. Eager loading
se automaticky přeskočí, když jsou aktivní interaktivní filtry podřádků, protože
filtrování per rodič spadne zpět na bezpečný per-parent dotaz.

## Reference voleb

| Metoda                                          | Účel                                     |
| ----------------------------------------------- | ---------------------------------------- |
| `subRows(string $relation)`                     | Zapnout podřádky z relace (vynechejte + nastavte `subRowView`/`subRowColumns` pro detail-řádkový režim) |
| `subRowColumns(array $columns)`                 | Sloupce pro dětskou tabulku              |
| `subRowQuery(Closure $cb)`                      | Tvarovat dotaz dětské relace             |
| `subRowsSortable(bool, ?string $default, string $direction)` | Řazení klikem na hlavičky + výchozí řazení |
| `subRowActions(array $actions)`                 | Řádkové akce per dítě                    |
| `subRowsLimit(?int)`                            | Omezit děti, zapnout „Zobrazit N dalších"|
| `subRowsFilterable(bool)`                       | Per-dítě interaktivní lišta filtrů       |
| `subRowsExpandable(bool)`                       | Povolit přepínač rozbalit/sbalit         |
| `subRowsDefaultExpanded(bool)`                  | Začít rozbalené                          |
| `subRowsToggleLabel(?string)`                   | Popisek sloupce přepínače                |
| `flattenSubRows(bool)`                          | Vykreslit děti jako ploché řádky         |
| `subRowView(string)`                            | Vlastní renderer dětí                    |

## Související dokumentace

- [Souhrny](summaries.md) — typy agregací, rozsahy, formátování, rollupy
- [Přehled tabulek](overview.md)
- [Sloupce](columns/index.md)
- [Akce](actions.md)
