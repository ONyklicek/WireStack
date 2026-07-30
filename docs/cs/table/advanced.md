---
order: 80
---

# Pokročilé funkce

---

## Obsah

1. [Podřádky (rozbalitelné řádky)](#podradky-rozbalitelne-radky)
2. [Souhrnná patička (agregáty)](#souhrnna-paticka-agregaty)
3. [Polling (auto-obnovení)](#polling-auto-obnoveni)
4. [Lazy loading](#lazy-loading)
5. [Optimalizace výkonu](#optimalizace-vykonu)
6. [Debugging dotazů](#debugging-dotazu)
7. [SQL debug](#sql-debug)
8. [Responzivní layout](#responzivni-layout)
9. [Přepínání sloupců](#prepinani-sloupcu)
10. [Kontextové menu řádku](#kontextove-menu-radku)
11. [Notifikace per tabulka](#notifikace-per-tabulka)
12. [Perzistence stavu v URL](#perzistence-stavu-v-url)
13. [Selektory pro browser testy](#selektory-pro-browser-testy)
14. [Vlastní pohledy](#vlastni-pohledy)

---

<a id="sub-rows-expandable-rows"></a>
## Podřádky (rozbalitelné řádky)

Trait `HasSubRows` zapíná rozbalitelné dětské řádky pro hierarchická data — objednávky → položky, kategorie → produkty, oddělení → zaměstnanci.

### Základní podřádky

```php
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;

$table
    ->model(Order::class)
    ->columns([
        TextColumn::make('number')->searchable()->sortable(),
        TextColumn::make('customer.name')->searchable(),
        TextColumn::make('total')->money('CZK')->sortable(),
        BadgeColumn::make('status')->colors([...]),
    ])
    ->subRows('items')
    ->subRowColumns([
        TextColumn::make('product.name'),
        TextColumn::make('quantity')->alignRight(),
        TextColumn::make('unit_price')->money('CZK'),
        TextColumn::make('subtotal')->money('CZK')->weight('bold'),
    ])
```

Uživatelé vidí vlevo ikonu šipky. Kliknutím se řádek rozbalí a zobrazí dětské řádky pod ním.

### Výchozí stav rozbalení

`subRowsDefaultExpanded()` určuje, kde řádky *začínají*; master chevron v hlavičce
sloupce s chevrony tento výchozí stav mění za běhu a volba přežije stránkování:

```php
$table->subRowsDefaultExpanded()
```

`flattenSubRows()` je zastaralý alias téhož — nikdy nic nezploštil, jen otevřel
všechny řádky. `toggleFlattenMode()` dál funguje a volá `toggleAllRowExpansion()`.

### Relace podřádků s eager loadingem

`->subRows()` přijímá tečkovou notaci pro eager-loaded relace:

```php
$table->subRows('items.product')
```

### Nezávislé filtrování podřádků

```php
$table->subRowsFilterable()
```

Když je zapnuto, tabulka vykreslí samostatné ovládání filtrů pro podřádky vedle hlavních filtrů.

### Vlastní pohled podřádku

Místo sloupců podřádku vykreslete úplně vlastní Blade pohled:

```php
$table->subRowView('components.order-items-detail')
```

```blade
{{-- resources/views/components/order-items-detail.blade.php --}}
<div class="p-4 bg-gray-50">
    <table class="w-full text-sm">
        @foreach($record->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ $item->quantity }}×</td>
                <td class="text-right font-bold">
                    {{ number_format($item->subtotal, 2) }} {{ $currency }}
                </td>
            </tr>
        @endforeach
        @if($showTotals)
            <tr class="border-t font-bold">
                <td colspan="2">Total</td>
                <td class="text-right">{{ number_format($record->total, 2) }} {{ $currency }}</td>
            </tr>
        @endif
    </table>
</div>
```

### Livewire stav podřádků

| Vlastnost | Typ | Popis |
|----------|------|-------------|
| `$expandedRows` | `array` | Klíče rozbalených rodičovských záznamů |
| `$flattenMode` | `bool\|null` | Výchozí stav rozbalení (zastaralý alias `rows.expandAll`) |

### API podřádků

```php
->subRows(string $relation)              // název Eloquent relace (tečková notace podporována)
->subRowColumns(array $columns)          // Column[] pro podřádky
->subRowView(string $view)              // vlastní Blade pohled (nahrazuje sloupce)
->subRowsFilterable(bool $filterable = true)
->subRowsDefaultExpanded(bool $expanded = true)
->subRowsExpandable(bool $expandable = true)
->subRowsLimit(?int $limit)             // max podřádků před "zobrazit více"
->subRowsToggleLabel(?string $label)
->flattenSubRows(bool $flatten = true)   // zastaralé: subRowsDefaultExpanded()
->hasSubRows(): bool
->getSubRowColumns(): array
```

---

<a id="summary-footer-aggregates"></a>
<a id="summary-footer"></a>
## Souhrnná patička (agregáty)

Trait `HasSummary` přidává agregátní řádky patičky — sum, avg, count, min, max, range.

### Souhrn na úrovni sloupce

```php
TextColumn::make('amount')
    ->money('CZK')
    ->summarize('sum', 'Total')

TextColumn::make('price')
    ->money('CZK')
    ->summarize('avg', 'Average')

TextColumn::make('id')
    ->summarize('count', 'Records')

TextColumn::make('rating')
    ->numeric(decimalPlaces: 1)
    ->summarize('min', 'Lowest')

TextColumn::make('score')
    ->numeric()
    ->summarize('max', 'Highest')

TextColumn::make('salary')
    ->money('CZK')
    ->summarize('range')          // ukáže "min - max"
```

### Souhrn na úrovni tabulky

```php
$table
    ->summarizeSum('amount', 'Total Amount')
    ->summarizeAvg('price', 'Avg Price')
    ->summarizeCount('id', 'Total Records')
    ->summarizeMin('rating', 'Min Rating')
    ->summarizeMax('score', 'Max Score')
    ->summarizeRange('salary', 'Salary Range')
```

### Rozsahy souhrnů

Argument `scope` (3. parametr `summarize()`) vybírá, které řádky se agregují.
Výchozí je `'query'` (všechny filtrované řádky, přes DB agregát).
Předejte `'page'` pro agregaci jen aktuální stránky v paměti. Sloupec může nést
více než jeden souhrn:

```php
TextColumn::make('amount')
    ->money('CZK')
    ->summarize('sum', 'Page Total', scope: 'page')    // jen aktuální stránka
    ->summarize('sum', 'Grand Total', scope: 'query')  // všechny filtrované řádky (výchozí)
```

Rozsahy: `'query'` (všechny filtrované), `'page'` (aktuální stránka), `'selection'`
(vybrané řádky), `'subRows'`.

### Vlastní formátování souhrnů

Předejte closuru `format` do `summarize()`, nebo použijte `summaryDecimals()` pro
numerické formátování:

```php
TextColumn::make('revenue')
    ->summarize('sum', format: fn (float $value) => number_format($value, 0, ',', ' ') . ' CZK')

TextColumn::make('total')
    ->summarize('sum')
    ->summaryDecimals(2)                 // → "1 234,50"
```

### Jak to funguje

1. **Rozsah page**: po načtení výsledků `HasSummary` projde Collection a spočítá
   agregát v PHP.
2. **Rozsah query**: samostatný `$query->sum('amount')` (nebo avg/count/min/max) se
   vykoná proti filtrovanému (ale nestránkovanému) datasetu.

### API souhrnů

Tyto metody žijí na **sloupci** (`HasSummary`):

```php
->summarize(
    string|Closure $type,           // 'sum','avg','count','min','max','range','distinct','median'
    ?string $label = null,
    string $scope = 'query',         // 'query' | 'page' | 'selection' | 'subRows'
    ?Closure $format = null,         // fn(mixed $value): string
    ?Closure $when = null,           // fn(Builder $query): Builder
)
->summaryDecimals(int $decimals, string $decimalSeparator = ',', string $thousandsSeparator = ' ')

// Zkratky — každá bere (?string $label = null, string $scope = 'query'):
->summarizeSum()      ->summarizeAvg()     ->summarizeCount()
->summarizeMin()      ->summarizeMax()     ->summarizeRange()
->summarizeDistinct() ->summarizeMedian()
```

---

<a id="polling-auto-refresh"></a>
## Polling (auto-obnovení)

Wire Table podporuje dva režimy pollingu: **na úrovni tabulky** (obnoví celou tabulku) a **na úrovni řádku/sloupce** (obnoví konkrétní buňky přes `PollColumn`).

### Polling na úrovni tabulky

```php
$table->poll('5s')                       // obnovit každých 5 sekund
```

Podporované intervaly: `'1s'`, `'2s'`, `'3s'`, `'5s'`, `'10s'`, `'15s'`, `'30s'`, `'60s'`.

### Keep alive (záložky na pozadí)

```php
$table->poll('5s')->pollKeepAlive()
```

Ve výchozím stavu Livewire zastaví polling, když je záložka prohlížeče skrytá. `pollKeepAlive()` to přepíše.

### Jen viditelné (viewport)

```php
$table->poll('5s')->pollOnlyVisible()
```

Pollovat jen když je element tabulky ve viewportu (používá IntersectionObserver).

### Podmíněný polling

```php
$table->poll('5s')
      ->pollWhen(fn () => Job::where('status', 'running')->exists())
```

Polling se spouští/zastavuje podle podmínky. Kontrolováno při každém intervalu.

### Vlastní poll metoda

```php
$table->poll('10s')->pollMethod('refreshData')
```

Místo plného re-renderu volá konkrétní Livewire metodu.

### Detekce změn (přeskočit nezměněné rendery)

```php
$table->poll('5s')->pollChangeDetection()
```

Každý poll normálně znovu spustí celý dotaz, souhrny a DOM morph, i když se nic
nezměnilo. Se zapnutou detekcí změn se mezi polly porovná levný checksum
(`COUNT(*)` + `MAX(updated_at)` filtrovaného dotazu, jeden SQL dotaz) — nezměněný
checksum přeskočí render úplně.

Modely bez timestampů spadnou zpět na vždy renderovat. Když rodičovské timestampy
nezachycují relevantní změny (např. rollup součty nad dětskými řádky), poskytněte
vlastní checksum:

```php
$table->poll('5s')
      ->pollChangeDetection(fn ($query) => (string) $query->max('synced_at'))
```

Closura dostane filtrovaný dotaz (bez řazení) a musí vrátit řetězec, který se
změní vždy, když je potřeba re-render.

Přeskočení renderu je vždy podmíněné tím, že požadavek nezměnil nic, co tabulka
zobrazuje. Livewire slučuje vše, co je pro jednu komponentu ve frontě, do jednoho
požadavku — takže poll tick (nebo uložení inline buňky, které render přeskakuje z
vlastního důvodu) může dorazit společně s tím, jak uživatel mění velikost stránky,
hledání, filtr nebo řazení. V takovém požadavku vyhrává změna a tabulka se
vyrenderuje; přeskočení by nechalo v prohlížeči starý pohled až do další akce
uživatele.

### Polling řádku/sloupce

Použijte `PollColumn` pro živé aktualizace per buňka bez obnovování celé tabulky:

```php
PollColumn::make('job_status')
    ->interval('3s')
    ->stateDisplays([...])
    ->stopWhen(fn ($state) => $state === 'completed')
    ->rowLevelPolling()
```

Kompletní API PollColumn viz [Sloupce — PollColumn](columns/poll.md).

### API pollingu

```php
->poll(string|Closure $interval)         // řetězec intervalu nebo Closure vracející ?string
->pollKeepAlive(bool $keepAlive = true)
->pollOnlyVisible(bool $onlyVisible = true)
->pollWhen(Closure $condition)           // fn() => bool
->pollMethod(string $method)             // název Livewire metody
->pollChangeDetection(bool|Closure $detector = true) // přeskočit render při nezměněných datech
```

---

<a id="lazy-loading"></a>
## Lazy loading

Odkládá počáteční render tabulky pro rychlejší načtení stránky. Tabulka se načte asynchronně poté, co je stránka viditelná.

```php
$table->lazy()
```

### Vlastní placeholder

```php
$table->lazy()
      ->lazyPlaceholder(
          '<div class="flex items-center justify-center p-16 text-gray-400">
              <svg class="w-8 h-8 animate-spin" ...>...</svg>
              <span class="ml-3">Loading table...</span>
          </div>'
      )
```

### Jak to funguje

1. Stránka se vykreslí okamžitě s placeholder HTML
2. Livewire odešle async volání pro načtení obsahu tabulky
3. Placeholder je nahrazen plně vykreslenou tabulkou
4. Následné interakce (řazení, filtrování, stránkování) jsou normální Livewire volání

`lazy()` odkládá i JavaScript, nejen dotaz a markup. Alpine bundly tabulky jdou
až s odloženým renderem a je to bezpečné ze dvou důvodů: Livewire nové `@assets`
z odpovědi načte a spustí **do konce**, teprve pak markup zamorfuje dovnitř — a
každý wireStack bundle registruje své Alpine komponenty bezpodmínečně, ne až
z listeneru na `alpine:init`. Ten proběhne přesně jednou, když Alpine nabootuje,
takže bundle, který dorazí později, by se jinak přihlásil k eventu, jenž už
nikdy nenastane, a nezaregistroval by nic. Factory tedy existuje dřív, než se
odložená tabulka inicializuje.

Vlastní `lazyPlaceholder()` mění jen viditelný skeleton — na to, co se načte,
nemá vliv. A pokud váš layout nese
[`@wireStackScripts`](../getting-started.md#javascriptove-assety), jsou sdílené
controllery v dokumentu už od prvního vykreslení, což je přesně to, co chcete
v aplikaci navigující přes `wire:navigate`.

### Kdy použít

- Dashboardové stránky s více tabulkami — načtěte každou lazy
- Tabulky se složitými dotazy — neblokujte počáteční vykreslení
- Tabulky pod foldem — načtěte jen když se k nim scrolluje (kombinujte s `pollOnlyVisible`)

---

<a id="performance-optimization"></a>
## Optimalizace výkonu

### Jednoduché stránkování

Eliminuje `COUNT(*)` dotaz:

```php
$table->simplePagination()
```

Kompromisy:
- Žádný text „Showing X of Y"
- Žádné odkazy na čísla stránek (jen Předchozí / Další)
- Ušetří jeden dotaz při načtení stránky u velkých tabulek

### Kurzorové stránkování

Stránkování bez offsetu, v konstantním čase:

```php
$table->cursorPagination()
```

Požadavky:
- Tabulka musí mít unikátní, řaditelný sloupec (obvykle `id` nebo `created_at`)
- Musí být nastaveno výchozí řazení

Kompromisy:
- Žádný náhodný přístup na stránku (jen Předchozí / Další)
- URL kurzory jsou neprůhledné řetězce
- Nelze kombinovat s operacemi `count()`

Nejlepší pro: real-time datové feedy, infinite scroll UI, tabulky > 1M řádků.

### Cachování dotazů

Cachovat výsledky dotazu na nakonfigurovaný TTL:

```php
$table->cacheQuery(ttl: 60)                    // 60 sekund, auto-generovaný klíč
$table->cacheQuery(ttl: 300, key: 'users')     // 5 minut, vlastní klíč
```

Cache klíč má dvě části: **namespace** říká, o kterou tabulku jde, a **otisk
stavu** říká, o který její pohled. Namespace je ve výchozím stavu SQL dotazu
s bindingy, nebo to, co předáte jako `key:`. Otisk pokrývá hledání, filtry,
sloupcové filtry, řazení, počet na stránku a číslo stránky — a připojuje se ke
*každému* namespace. Vlastní `key:` tedy entries scopuje, nenahrazuje jejich
identitu.

Je to podstatné, protože cachovaná tabulka servíruje stránkovaný *výřez*, ne
dotaz: `perPage` a stránka se aplikují uvnitř cachovaného callbacku, takže se do
SQL nikdy nedostanou, a vlastní klíč nic neví o řazení ani o aktivních
filtrech. Kdyby cokoli z toho v klíči chybělo, tabulka by na celý TTL zamrzla —
změna počtu na stránku by dál servírovala řádky nacachované pod stejným klíčem.

Pro scopování podle tenanta nebo uživatele buď předejte `key:`, nebo na
komponentě přepište `generateQueryCacheKey()`; otisk stavu se připojí tak jako
tak.

Používá `Cache::remember()` — funguje s jakýmkoli Laravel cache driverem.

### Chunkované hromadné zpracování

Zpracovat záznamy po dávkách pro paměťově efektivní hromadné operace:

```php
$table->chunk(500, function (Collection $records) {
    foreach ($records as $record) {
        $record->process();
    }
})
```

Interně používá `chunkById()` pro konzistentní pořadí.

### Srovnání výkonu

| Funkce | Dotazy | Nejlepší pro |
|---------|---------|----------|
| Standardní stránkování | 2 (count + select) | < 100k řádků |
| Jednoduché stránkování | 1 (select) | 100k – 1M řádků |
| Kurzorové stránkování | 1 (select) | > 1M řádků |
| Cache + standardní | 0-2 (cache hit/miss) | Často prohlížené, zřídka aktualizované |
| Lazy loading | Totéž jako výše (odloženo) | Rychlejší počáteční vykreslení |

---

<a id="query-debugging"></a>
## Debugging dotazů

### Inspekce QueryPlan

Získejte immutable `QueryPlan`, abyste přesně viděli, co engine udělá:

```php
$plan = $table->debugQueryPlan();

// Joiny
foreach ($plan->joins as $join) {
    echo "{$join->type} JOIN {$join->table} ON {$join->first} {$join->operator} {$join->second}\n";
}

// Eager loady
dump($plan->eagerLoads);     // ['author', 'tags', 'category']

// Agregáty
dump($plan->aggregates);      // [AggregateClause(relation: 'comments', function: 'count')]

// Filtry
dump($plan->filters);         // [FilterClause(column: 'role', operator: '=', value: 'admin')]

// Hledání
dump($plan->searchClauses);   // [SearchClause(columns: ['name','email'], term: 'john')]

// Řazení
dump($plan->sortClauses);     // [SortClause(column: 'name', direction: 'asc')]
```

### Raw SQL

```php
$sql = $table->toSql();
// "SELECT users.* FROM users LEFT JOIN departments ON ... WHERE ... ORDER BY ..."
```

### Metadata sloupců

```php
$info = $table->getColumnsInfo();
// Pole metadat sloupců: DB typ, nullable, schopnosti, cesty relací

$dbColumns = $table->getDatabaseColumns();
// ['id', 'name', 'email', 'role', 'created_at', ...]

$dbInfo = $table->getDatabaseColumnsInfo();
// ['name' => ['type' => 'varchar', 'nullable' => false, ...], ...]
```

---

<a id="sql-debug"></a>
## SQL debug

Trait `HasSqlDebug` (součást `WithTable`) poskytuje utility pro interpolaci SQL:

```php
// Získat raw SQL s dosazenými bindingy (jen pro debugging!)
$rawSql = $this->builderToSql($query);
// "SELECT * FROM users WHERE role = 'admin' AND created_at >= '2024-01-01'"

// Dosadit bindingy do prepared statementu
$interpolated = $this->interpolateSql($sql, $bindings);
```

**Varování**: Interpolované SQL je jen pro debugging. Nikdy ho nevykonávejte přímo — používejte parametrizované dotazy.

### Použití ve vývoji

```php
class UserTable extends Component
{
    use WithTable;

    public function debugQuery(): void
    {
        $table = $this->table(Table::make());
        $query = $this->buildTableQuery($table);

        logger()->debug('Table SQL', [
            'sql' => $this->builderToSql($query),
            'plan' => $table->debugQueryPlan(),
        ]);
    }
}
```

---

<a id="responsive-layout"></a>
## Responzivní layout

### Naskládané na mobilu

Pod breakpointem se sloupce naskládají svisle jako páry label-hodnota:

```php
$table->stackedOnMobile(true, 'md')   // 2. arg = breakpoint, pod kterým se skládá (výchozí 'md')
```

V naskládaném režimu:
- Každý řádek se stane kartou
- Každý sloupec se vykreslí jako `Label: Value`
- `visibleFrom()`/`hiddenFrom()` sloupce stále platí

Akce řádku se v hlavičce každé karty vykreslují vedle sebe. Když má řádek více
akcí, sbal je do jednoho rozbalovacího menu, aby hlavička zůstala přehledná:

```php
$table
    ->stackedOnMobile()
    ->collapseActionsOnMobile()   // jeden spouštěč "⋮" na kartu místo akcí vedle sebe
```

Sbalení se zapne, až když má řádek **3 a více** akcí; při méně je karta nechá
vedle sebe. Práh nastavíš druhým argumentem:

```php
->collapseActionsOnMobile(threshold: 2)   // sbalit od 2 akcí
->collapseActionsOnMobile(threshold: 1)   // sbalit vždy
```

Ovlivněny jsou pouze naskládané karty na mobilu — desktopová tabulka si ponechá
akční tlačítka vedle sebe. Případné existující `ActionGroup` se sloučí do jednoho
mobilního menu (oddělovače se při sloučení zahodí) a karta s jedinou viditelnou
akcí ji stále zobrazí přímo. Menu přebírá nastavení tabulky `sheetOnMobile()` /
`mobileBreakpoint()` (na malých obrazovkách se ve výchozím stavu chová jako
spodní sheet).

### Anatomie karty

Karta je záznam, ne přestrojené pořadí sloupců. Hierarchii nesou čtyři pojmenované
sloty — co to je, čí to je, za kolik — a zbytek spadne do mřížky popisek/hodnota
pod nimi:

```text
┌──────────────────────────────────────────────┐
│ INV-1001                        9 350 Kč  ⋮  │  titulek · metrika · akce
│ Northwind Traders                            │  podřádek
│ [ zaplaceno ]                                │  meta
│ ─────────────────────────────────────────    │
│ POZNÁMKA        REFERENCE                    │  všechno ostatní
│ První objednávka 2026/114                    │
└──────────────────────────────────────────────┘
```

Deklarovat kvůli tomu nemusíte nic: sloty se odvodí ze sloupců, které už máte.

| Slot | Odvozeno z |
| ---- | ---------- |
| `title` | první viditelný sloupec |
| `metric` | poslední sloupec zarovnaný vpravo — tedy to, co dělá `money()` a `numeric()` |
| `meta` | badge sloupce |
| `subtitle` | první sloupec, který si nevzal jiný slot |
| mřížka detailů | všechno zbylé |

Když odvození hádá špatně, řekněte to — buď u sloupce:

```php
TextColumn::make('total')->money()->mobileMetric(),
BadgeColumn::make('status')->mobileMeta(),
TextColumn::make('reference')->mobileDetail(),   // ať zůstane mimo hlavičku
```

…nebo pro celou tabulku, což přebije odvození i deklarace u sloupců:

```php
use NyonCode\WireTable\Support\MobileCardConfig;

$table->mobileCard(fn (MobileCardConfig $card) => $card
    ->title('number')
    ->subtitle('customer')
    ->metric('total')
    ->meta(['status', 'due_at']));
```

Metrika sedí vpravo na řádku s titulkem v tabulárních číslicích, takže se sloupec
částek dá porovnávat po pravé hraně místo čtení karta po kartě.

### Podřádky na kartě

Rozbalené děti se vykreslí jako seznam, ne jako vnořená tabulka z desktopu: název
vlevo, jeho částka na stejné pravé hraně jako metrika karty, doplňující detail pod
tím.

```text
│ 3 položky                                 ⌄  │
│ ──────────────────────────────────────────── │
│ 27" monitor                    5 600 Kč   ⋮  │
│ Jednotka: 5 600 Kč                           │
│ Mechanická klávesnice          2 400 Kč   ⋮  │
│ Jednotka: 1 200 Kč                           │
│ Mezisoučet                     9 350 Kč      │
```

Mezisoučty za rodiče, tlačítko „Zobrazit dalších N“ i akce dětí tady fungují —
dřív to uměl jen desktop, zatímco karta slila všechny děti do jedné nerozlišitelné
mřížky.

Akce dětí se vždy sbalí pod jeden spouštěč `⋮`, ať `collapseActionsOnMobile()`
říká cokoli: řádek dítěte je užší než karta, která ho drží, a dvě tlačítka s
popiskem tam rozdrtí název položky na tři tečky.

Sbalený přepínač uvádí počet dětí (`3 položky`), když je číslo už v paměti, a
jinak se vrátí k `Detail` — sbalený řádek nemá eager-loadované děti, takže spočítat
je by stálo jeden dotaz na kartu. Přidejte do základního dotazu `->withCount('items')`
a každá karta svůj počet uvede zadarmo.

### Součty na kartě

Desktopové součty bydlí v `<tfoot>` tabulky, kterou skládané karty skrývají —
stohovaná tabulka tedy neukazovala žádné součty, což je v účetní tabulce zrovna
to číslo, kvůli kterému tam uživatel je. Nově se vykreslí pod kartami jako řádky
popisek/hodnota, na stejné pravé hraně jako metrika každé karty, se stejným
přepínačem rozsahu *Vše / Tato stránka / Výběr*, jaký má desktopová patička:

```text
│ INV-1003                          8 450 Kč │
├────────────────────────────────────────────┤
│ Zobrazeno:            [ Vše ][Tato stránka]│
│ Celkem položek · Položky                  7 │
│ Celkem · Celkem                  35 900 Kč  │
│ Průměr · Celkem                  11 967 Kč  │
```

Není co nastavovat — sloupec se `summarize*()` dostane svůj součet sem stejně
jako do patičky tabulky, a celkové součty podřádků jdou stejnou cestou.

### Breakpointy sloupců

```php
// Viditelné od md nahoru (skryté na mobilu)
TextColumn::make('email')->visibleFrom('md')

// Skryté od lg nahoru (viditelné jen na mobilu/tabletu)
TextColumn::make('phone')->hiddenFrom('lg')

// Zkratky
TextColumn::make('address')->onlyOnDesktop()       // ≥lg
TextColumn::make('avatar')->onlyOnMobile()          // <md
TextColumn::make('subtitle')->onlyOnTabletAndUp()   // ≥md
TextColumn::make('metadata')->onlyOnLargeScreens()  // ≥xl
```

### Mobilní zobrazení per záznam

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} ({$record->email})")
```

---

<a id="column-toggling"></a>
## Přepínání sloupců

Uživatelé mohou zobrazit/skrýt přepínatelné sloupce přes dropdown výběru sloupců:

```php
// Označit konkrétní sloupce jako přepínatelné
TextColumn::make('phone')
    ->toggleable()                  // uživatel může skrýt/zobrazit
    ->hidden()                      // začít skryté (uživatel může zapnout)

TextColumn::make('notes')
    ->toggleable()
    ->visibleFrom('lg')             // výchozí viditelné od lg, ale uživatel může přepsat
```

Ve výchozím stavu množina zobrazených/skrytých sloupců žije jen po dobu života
komponenty (po úplném reloadu stránky se resetuje).

### Zapamatování rozvržení pro každého uživatele

Zavolej `rememberColumns()` se stabilním klíčem — tabulka při mountu načte uložené
rozvržení aktuálního uživatele a při každém přepnutí sloupce ho uloží, takže si
každý uživatel drží vlastní uspořádání sloupců i po reloadu. V přepínači se
objeví tlačítko „Obnovit sloupce“ pro návrat na výchozí nastavení.

```php
$table
    ->columns([
        TextColumn::make('name'),
        TextColumn::make('email')->toggleable(),
        TextColumn::make('phone')->toggleable()->hidden(),
    ])
    ->rememberColumns('users-index'); // stabilní, unikátní pro tabulku
```

Preference driver scopuje na `auth()->user()`, takže **jeden klíč slouží všem
uživatelům** — funguje pro libovolný počet tabulek (různé klíče) i uživatelů.
Uložený sloupec, který už neexistuje (přejmenovaný/odebraný), je při načtení
ignorován.

**Kam se ukládá** řídí driver zvolený v `config('wire-table.preferences')`:

| Driver     | Persistence                                     | Nastavení |
|------------|-------------------------------------------------|-----------|
| `null`     | Neukládá se (výchozí)                            | — |
| `session`  | Session uživatele                               | žádné |
| `database` | Řádek `table_preferences` na (uživatel, tabulka)| publish + migrace |

```php
// config/wire-table.php
'preferences' => [
    'default' => env('WIRE_TABLE_PREFERENCES_DRIVER', 'null'), // přihlášení uživatelé
    'guest'   => env('WIRE_TABLE_PREFERENCES_GUEST_DRIVER', 'session'), // návštěvníci
    // ...
],
```

Pro database driver publikuj a spusť migraci:

```bash
php artisan vendor:publish --tag="wire-table::migrations"
php artisan migrate
```

Driver lze přepsat pro jednu tabulku (např. vynutit databázi i když je globální
výchozí `session`), nebo zapojit vlastní úložiště implementující
`TablePreferenceDriver`:

```php
$table
    ->rememberColumns('reports')
    ->preferenceDriver(app(DatabasePreferenceDriver::class));
```

---

<a id="row-context-menu"></a>
## Kontextové menu řádku

Nech pokročilé uživatele **kliknout pravým tlačítkem na řádek** a otevřít menu
akcí u kurzoru — zkratka vedle sloupce s akcemi. Akce menu se definují
**samostatně** přes `rowContextMenu([...])` (nejsou to akce z `->actions()`
toolbaru), takže je menu explicitní, ne implicitní kopie tlačítek řádku — pokud
je chceš stejné, předej stejné objekty. Používá stejný styl položek jako dropdown
action-group.

```php
$table
    ->columns([/* ... */])
    ->actions([EditAction::make()])            // toolbar řádku
    ->rowContextMenu([                          // samostatné pravé menu
        ViewAction::make(),
        EditAction::make(),
        DeleteAction::make(),
    ]);
```

- Menu ukáže přesně **viditelné** akce menu (skryté/neautorizované se vynechají);
  řádek bez viditelné akce menu neukáže.
- V jeden okamžik je otevřené **jen jedno** menu — kliknutí pravým na jiný řádek
  předchozí zavře.
- Je připnuté ke kurzoru a udrží se ve viewportu; zavře se kliknutím mimo,
  klávesou `Escape`, scrollem nebo po zvolení akce (ta se spustí normálně, např.
  otevře svůj modal).
- Skupiny akcí se do menu zploští.
- Jde o funkci pro **desktop ukazatel** — dotyková zařízení kontextové menu
  nemají, takže sloupec s akcemi zůstává hlavním ovládáním.

---

<a id="notifications-per-table"></a>
## Notifikace per tabulka

Přepsat globální notifikační driver pro konkrétní tabulku:

```php
$table->notificationDriver('livewire')   // použít Livewire události pro tuto tabulku
```

Užitečné, když různé části vaší aplikace používají různá notifikační UI.

---

<a id="url-state-persistence"></a>
## Perzistence stavu v URL

Přetrvat stav tabulky (hledání, řazení, počet na stránku, filtry) v URL pro odkazy, které lze uložit do záložek a sdílet:

```php
public function table(Table $table): Table
{
    return $table
        ->model(User::class)
        ->queryString()
        ->columns([...])
        ->filters([...]);
}
```

URL pak vypadají takto:

```text
/users?search=john&sort=name&direction=desc&per_page=25&filter_role=admin
```

Sledované parametry:

| Parametr | Stav | Poznámky |
|---|---|---|
| `search` | globální hledání | jen když je tabulka searchable |
| `sort`, `direction` | stav řazení | přijímají se jen názvy řaditelných sloupců |
| `per_page` | velikost stránky | přijímají se jen hodnoty z `perPageOptions()` |
| `filter_{name}` | hodnota filtru | jeden parametr na filtr |
| `page` | aktuální stránka | zpracováno Livewire `WithPagination`; stránka za koncem se zakotví na poslední zaplněnou |

Vícepolní filtry se rozšíří na parametry se suffixem: `NumberRangeFilter`
se stane `filter_price_min` / `filter_price_max`, rozsahový `DateFilter`
se stane `filter_created_at_from` / `filter_created_at_to`. Filtry používající
`multiple()` přijímají pole syntax (`filter_status[]=active&filter_status[]=trial`).

Příchozí URL hodnoty se validují proti konfiguraci tabulky —
neznámé sloupce řazení, hodnoty per-page mimo `perPageOptions()` a
parametry pro neznámé nebo skryté filtry se ignorují. Stejná kontrola běží
i na živé `wire:model` cestě, takže podvržený Livewire payload si nemůže
vyžádat velikost stránky, kterou tabulka nenabízí.

### Více tabulek na stránku

Názvy parametrů jsou globální per URL. Když se na stejné stránce vykreslí dvě
tabulky s perzistencí v query stringu, dejte každé prefix:

```php
$table->queryString('orders_');   // ?orders_search=…&orders_filter_status=…
```

### Poznámky

- URL naplnění vyhrává nad hodnotami `defaultSort()` / filter `default()`.
- Filtry, jejichž názvy obsahují tečky (filtry relací jako `author.name`),
  nejsou sledovány v URL.
- URL se aktualizuje přes `history.replaceState`, takže psaní do vyhledávacího
  pole nezaplaví historii prohlížeče; parametry zase zmizí, když se stav vrátí
  na výchozí.

---

<a id="browser-testing-selectors"></a>
## Selektory pro browser testy

Každá interaktivní část tabulky nese stabilní `data-testid` (a přístupný
název/role tam, kde je ovládač jen ikona), takže [Pest v4 Browser
Testing](https://pestphp.com/docs/browser-testing) na ni umí cílit na
uživatelské úrovni bez křehkých CSS selektorů.

| Část | Selektor |
|------|----------|
| Vyhledávací pole | `data-testid="table-search"` (+ `aria-label`) |
| Trigger filtrů | `data-testid="table-filters-trigger"` |
| Reset filtrů | `data-testid="table-filter-reset"` |
| Chip filtru / odebrání | `data-testid="filter-chip-{název}"` / `filter-chip-remove-{název}` |
| Přepínač sloupců | `data-testid="table-column-toggle"` |
| Výběr počtu na stránku | `data-testid="table-per-page"` |
| Stránkování | `data-testid="table-page-prev"` / `table-page-next` / `table-page-{n}` |
| Řaditelná hlavička | `data-testid="table-sort-{sloupec}"` |
| Buňka filtru sloupce | `data-testid="table-filter-{sloupec}"` |
| Buňka těla | `data-testid="table-cell-{sloupec}"` (+ `data-column`) |
| Inline-edit buňka | `data-testid="table-editable-{sloupec}"` |
| Řádek | `data-testid="table-row"` + `data-row-key="{klíč}"` (mobilní karta: `table-card`) |
| Vybrat vše / řádek / karta | `data-testid="table-select-all"` / `table-row-select` / `table-card-select` (`role="checkbox"`, `aria-label`) |
| Rozbalení podřádku | `data-testid="table-row-expand"` (`aria-expanded`) |
| Akce řádku | `data-testid="action-{název}"` (+ `aria-label`) |
| Hlavička / bulk / menu akce | `data-testid="header-action-{název}"` / `bulk-action-{název}` / `menu-action-{název}` |
| Akce prázdného stavu | stejné testid jako její druh (`action-{název}` / `header-action-{název}`) — při `stackedOnMobile()` sedí **dvakrát**, jednou za každý layout, takže vybírejte ten viditelný |
| Bulk lišta / zrušit výběr | `data-testid="table-bulk-bar"` / `table-deselect"` |
| Ovládač filtru v panelu | `data-testid="filter-{name}"` (vstup uvnitř Select / Ternary / vlastního panelového filtru — odlišné od buňky hlavičky `table-filter-{column}`) |
| Trigger action group | `data-testid="action-group-trigger"` |
| Kopírovací tlačítko buňky | `data-testid="cell-copy"` |
| Buňka ButtonColumn | `data-testid="column-button"` |
| Přepínač pollingu | `data-testid="polling-toggle"` |
| Ovládače sub-řádků | `data-testid="subrows-master-toggle"` / `subrows-expand-all-rows` / `subrows-reset-filters` / `subrows-show-more` / `subrows-sort-{column}` |
| Přepínač rozsahu souhrnu | `data-testid="summary-scope-{value}"` |

Akce jdou cílit i přes viditelný popisek a volby filtru přes jejich text —
preferuj je pro nejvěrnější uživatelské asserce:

```php
it('filtruje uživatele podle role', function () {
    $page = visit('/users');

    $page->assertSee('Ann')->assertSee('Bob');

    // Otevři searchable Role filtr a vyber hodnotu (uživatelská úroveň).
    $page->click('@table-filter-role')       // data-testid
        ->fill('search', 'Man')
        ->click('Manager');

    $page->assertSee('Bob')->assertDontSee('Ann');
});

it('upraví první řádek přes jeho akci', function () {
    visit('/users')
        ->within('[data-row-key="1"]', fn ($row) => $row->click('@action-edit'))
        ->assertSee('Upravit uživatele');
});
```

Celá aktivní plocha — vyhledávání, řazení, filtry sloupců, výběr řádků, akce,
kontextové menu i přepínač sloupců — je takto dosažitelná.

**Mimo tabulku** platí stejná konvence napříč sdíleným UI, takže celý tok
(otevřít modal, vyplnit formulář, potvrdit) je plně mapovatelný:

Konvence názvů (aby šel odvodit jakýkoli hook): **každé** pole formuláře má
kontejner `form-field-{statePath}`; interaktivní typy navíc vystavují ovládač
`form-{typ}-{statePath}`, jehož podovládače přidávají `-{akce|hodnota|index}`.
Prosté text / number inputy mají jen kontejner (cil ho, nebo `<input>` uvnitř) —
žádný `form-text-{path}` hook neexistuje.

| Plocha | Selektor |
|--------|----------|
| Každé pole formuláře (kontejner) | `data-testid="form-field-{statePath}"` (+ `data-field`) |
| Toggle / checkbox / slider | `form-toggle-{path}`, `form-checkbox-{path}`, `form-slider-{path}` |
| Radio / checkbox-list volby | `form-radio-{path}-{value}`, `form-checklist-{path}-{value}` (+ `-select-all` / `-deselect-all` / `-search`) |
| Repeater / key-value | `form-repeater-{path}-add|remove-{i}|reorder-{i}`, `form-keyvalue-{path}-add|remove-{i}` |
| File / tags | `form-file-{path}-dropzone|remove-{i}`, `form-tags-{path}-remove-{i}` |
| Date-time picker | `form-datetime-{path}-trigger|prev-month|next-month|day-{d}|hours-up|hours-down|minutes-up|minutes-down|seconds-up|seconds-down|clear|done` |
| Color / rating / OTP | `form-color-{path}` (+ `-hex` / `-swatch-{barva}`), `form-rating-{path}-star-{n}`, `form-otp-{path}-{i}` |
| Editory (markdown/rich/tiptap) | `form-editor-{path}` (tělo) + `-{command|index}` toolbar tlačítka + `-write` / `-preview` taby |
| Field / affix / hint akce | `field-action-{path}-{name}` |
| Searchable select (formuláře + filtry) | `select-trigger` / `select-search` / `select-option-{value}` / `select-clear`; triggery akcí volby `form-select-{path}-create-option` / `-edit-option`; create/edit-option modaly: `select-create-save|cancel`, `select-edit-save|cancel` |
| MorphToSelect | `form-select-{path}-type` (typ morphu) / `form-select-{path}-record` (výběr záznamu) |
| Modal / slide-over / potvrzení | `modal-close`, `slide-over-close`, `modal-cancel` / `modal-submit`, `modal-back` / `modal-next`, `confirmation-confirm` / `confirmation-cancel`, `modal-footer-action-{name}` |
| Wizard / tabs / sekce / callout | `wizard-step-{i}` / `wizard-back` / `wizard-next`, `tab-{i}`, `section-toggle`, `callout-dismiss` |
| Toasty | `toast-dismiss`, `toast-action-{i}`, `toast-expand` |
| Akce infolistu | `infolist-action-{name}` |
| Sortable úchyt | `sortable-handle` (`role="button"`, `aria-label`) |

---

<a id="custom-views"></a>
## Vlastní pohledy

### Vlastní pohled tabulky

```php
$table->view('my-custom-table-view')
```

Wire Table resolvuje pohledy s podporou namespace. Výchozí pohledy můžete publikovat a přepsat:

```bash
php artisan vendor:publish --tag=wire-table::views
```

Publikováno do `resources/views/vendor/wire-table/`.

### Trait HasView

Trait `HasView` poskytuje logiku resolvování pohledů:

```php
// Resolvuje v pořadí:
// 1. Explicitní pohled nastavený přes ->view()
// 2. Pohled balíčku: wire-table::table
$table->getView();
```

---

## Kompletní příklad z reálného světa

```php
class OrderTable extends Component
{
    use WithTable;

    protected $queryString = [
        'tableSearch' => ['except' => '', 'as' => 'q'],
        'tableSortColumn' => ['except' => '', 'as' => 'sort'],
        'tableFilters' => ['except' => [], 'as' => 'f'],
    ];

    public function table(Table $table): Table
    {
        return $table
            ->model(Order::class)
            ->modifyQueryUsing(fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->columns([
                TextColumn::make('number')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                StackedColumn::make('customer')
                    ->avatar('customer.avatar_url')
                    ->primary('customer.name')
                    ->secondary('customer.email')
                    ->circular()
                    ->searchable()
                    ->searchColumns(['customer.name', 'customer.email']),

                TextColumn::make('items.count')
                    ->label('Items')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total')
                    ->money('CZK')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold')
                    ->summarize('sum', 'Page Total', scope: 'page')
                    ->summarize('sum', 'Grand Total', scope: 'query'),

                BadgeColumn::make('status')
                    ->colors([
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'success',
                        'delivered' => 'primary',
                        'cancelled' => 'danger',
                    ])
                    ->icons([
                        'pending' => 'clock',
                        'processing' => 'refresh',
                        'shipped' => 'truck',
                        'delivered' => 'check',
                        'cancelled' => 'x',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->size('sm')
                    ->textColor('gray')
                    ->visibleFrom('lg'),

                PollColumn::make('shipping_status')
                    ->interval('30s')
                    ->badge()
                    ->colors(['success' => 'delivered', 'info' => 'in_transit', 'gray' => 'waiting'])
                    ->pollWhile(fn ($state) => $state === 'in_transit')
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple()
                    ->default(['pending', 'processing']),

                DateFilter::make('created_at')
                    ->range()
                    ->fromLabel('From')
                    ->toLabel('Until'),

                NumberRangeFilter::make('total')
                    ->min(0)->max(1000000)->step(100),

                TernaryFilter::make('has_invoice')
                    ->label('Invoice Generated')
                    ->query(fn (Builder $q, bool $value) => $value
                        ? $q->whereNotNull('invoice_id')
                        : $q->whereNull('invoice_id')),
            ])
            ->actions([
                Action::make('view')
                    ->icon('eye')
                    ->url(fn ($r) => route('orders.show', $r)),

                ActionGroup::make('more', [
                    Action::make('invoice')
                        ->icon('document')
                        ->visible(fn ($r) => $r->status !== 'draft')
                        ->action(fn ($r) => $r->generateInvoice()),
                    Action::make('duplicate')
                        ->icon('copy')
                        ->action(fn ($r) => $r->replicate()->save()),
                    Action::divider(),
                    Action::make('cancel')
                        ->icon('x')
                        ->color('danger')
                        ->visible(fn ($r) => ! in_array($r->status, ['delivered', 'cancelled']))
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this order?')
                        ->action(fn ($r) => $r->cancel()),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('export')
                    ->icon('download')
                    ->action(fn ($records) => $this->export($records)),
                DeleteBulkAction::make(),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('New Order')
                    ->icon('plus')
                    ->url(route('orders.create')),
            ])
            ->subRows(fn ($record) => $record->items)
            ->subRowColumns([
                TextColumn::make('product.name'),
                TextColumn::make('quantity')->alignCenter(),
                TextColumn::make('unit_price')->money('CZK'),
                TextColumn::make('subtotal')->money('CZK')->weight('bold'),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->paginated()
            ->perPage(25)
            ->perPageOptions([10, 25, 50, 100])
            ->selectable()
            ->striped()
            ->hoverable()
            ->stackedOnMobile()
            ->emptyState(
                heading: 'No orders found',
                description: 'Create your first order to get started.',
                icon: 'shopping-cart',
            )
            ->emptyStateActions([
                Action::make('createFirstOrder')
                    ->label('New Order')
                    ->icon('plus')
                    ->url(route('orders.create')),
            ]);
    }
}
```
