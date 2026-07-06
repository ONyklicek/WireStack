---
order: 80
---

# Pokročilé funkce

---

## Obsah

1. [Podřádky (rozbalitelné řádky)](#sub-rows-expandable-rows)
2. [Souhrnná patička (agregáty)](#summary-footer-aggregates)
3. [Polling (auto-obnovení)](#polling-auto-refresh)
4. [Lazy loading](#lazy-loading)
5. [Optimalizace výkonu](#performance-optimization)
6. [Debugging dotazů](#query-debugging)
7. [SQL debug](#sql-debug)
8. [Responzivní layout](#responsive-layout)
9. [Přepínání sloupců](#column-toggling)
10. [Notifikace per tabulka](#notifications-per-table)
11. [Perzistence stavu v URL](#url-state-persistence)
12. [Vlastní pohledy](#custom-views)

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

### Rozbalit vše ve výchozím stavu

```php
$table->subRowsDefaultExpanded()
```

Všechny řádky začnou rozbalené.

### Flatten režim

Zobrazit všechny podřádky inline bez rozbalování/sbalování — plochý pohled:

```php
$table->flattenSubRows()
```

Uživatelé mohou přepnout flatten režim přes Livewire metodu `toggleFlattenMode()`.

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
| `$flattenMode` | `bool` | Přepínač plochého pohledu |

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
->flattenSubRows(bool $flatten = true)
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

Cache klíč zahrnuje hash aktuálního stavu (hledání, filtry, řazení, stránka), takže různé stavy se cachují nezávisle. Aktuální stránka je vždy součástí klíče — i s vlastním `key:` — protože stránkování se aplikuje uvnitř cachovaného callbacku.

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

Stav je uložen v `$hiddenColumns` (Livewire vlastnost). Přetrvá po dobu session.

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
| `page` | aktuální stránka | zpracováno Livewire `WithPagination` |

Vícepolní filtry se rozšíří na parametry se suffixem: `NumberRangeFilter`
se stane `filter_price_min` / `filter_price_max`, rozsahový `DateFilter`
se stane `filter_created_at_from` / `filter_created_at_to`. Filtry používající
`multiple()` přijímají pole syntax (`filter_status[]=active&filter_status[]=trial`).

Příchozí URL hodnoty se validují proti konfiguraci tabulky —
neznámé sloupce řazení, hodnoty per-page mimo `perPageOptions()` a
parametry pro neznámé nebo skryté filtry se ignorují.

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
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'shipped',
                        'primary' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->icons([
                        'clock' => 'pending',
                        'refresh' => 'processing',
                        'truck' => 'shipped',
                        'check' => 'delivered',
                        'x' => 'cancelled',
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
                    ->query(fn (Builder $q, $value) => $value === '1'
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
            );
    }
}
```
