---
order: 10
---

# Wire Table

Livewire tabulková komponenta enterprise úrovně pro Laravel. Závisí na `wire-core` a `wire-forms`.

## Instalace

```bash
composer require nyoncode/wire-table
```

Přidejte do Tailwind content cest:
```js
module.exports = {
    content: [
        // ...
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    ],
}
```

Publikování konfigurace (volitelné):
```bash
php artisan vendor:publish --tag=wire-table::config
```

---

## Rychlý start

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

class UserTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table // [tl! focus:start]
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied!'),

                BadgeColumn::make('role')
                    ->colors([
                        'admin' => 'primary',
                        'editor' => 'success',
                        'viewer' => 'gray',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->size('sm')
                    ->textColor('gray'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ]),
            ])
            ->actions([
                Action::make('edit')
                    ->icon('pencil')
                    ->url(fn (User $r) => route('users.edit', $r)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->searchable()
            ->paginated()
            ->striped()
            ->hoverable(); // [tl! focus:end]
    }

    public function render()
    {
        return view('livewire.user-table');
    }
}
```

```blade
{{-- resources/views/livewire/user-table.blade.php --}}
<div>
    {{ $this->table }}
</div>
```

A to je vše. Tabulka zvládá hledání, řazení, filtrování, stránkování, akce a inline editaci — vše s nulovou JavaScriptovou konfigurací.

---

## Trait WithTable

Trait `WithTable` je vrstva Livewire integrace. Poskytuje:

- Všechny Livewire-vázané veřejné vlastnosti (hledání, řazení, filtry, stránkování, výběr)
- Lifecycle hooky (`mountWithTable`, property watchery)
- Sestavení dotazu přes `TableQueryService`
- Pipeline vykonání akcí
- Pipeline inline editace
- Správu modalů
- Rozbalení řádků (podřádky)
- Přepínání viditelnosti sloupců
- SQL/query debugging

### Veřejné vlastnosti (Livewire stav)

Ty se automaticky synchronizují s prohlížečem přes Livewire:

| Vlastnost | Typ | Výchozí | Popis |
|----------|------|---------|-------------|
| `$tableSearch` | `?string` | `null` | Aktuální hledaný výraz |
| `$tableSortColumn` | `string` | `''` | Název aktuálního sloupce řazení |
| `$tableSortDirection` | `string` | `'asc'` | `'asc'` nebo `'desc'` |
| `$tablePerPage` | `int` | `10` | Záznamů na stránku |
| `$tableFilters` | `array` | `[]` | Aktivní hodnoty filtrů: `['role' => 'admin', ...]` |
| `$columnFilters` | `array` | `[]` | Hodnoty filtrů na úrovni sloupce |
| `$selectedRecords` | `array` | `[]` | Primární klíče vybraných záznamů |
| `$hiddenColumns` | `array` | `[]` | Názvy sloupců skrytých uživatelem |
| `$expandedRows` | `array` | `[]` | Primární klíče rozbalených řádků (podřádky) |
| `$flattenMode` | `bool\|null` | `null` | Výchozí stav rozbalení (`null` = dle `subRowsDefaultExpanded()`) |

### Livewire metody (wire: volatelné)

Ty se volají z Alpine.js nebo Livewire direktiv v Blade pohledech:

| Metoda | Zavolána když |
|--------|------------|
| `sortTable($column)` | Uživatel klikne na hlavičku sloupce |
| `resetSort()` | Uživatel resetuje řazení |
| `updatedTableSearch($value)` | Změní se vstup hledání |
| `updatedTableFilters()` | Změní se hodnota filtru |
| `updatedColumnFilters()` | Změní se filtr sloupce |
| `updatedTablePerPage()` | Změní se výběr počtu na stránku |
| `toggleColumnVisibility($name)` | Uživatel skryje/zobrazí sloupec |
| `selectRecord($key)` | Přepnut checkbox |
| `selectAll()` | Přepnuto „vybrat vše" |
| `deselectAll()` | Kliknuto „zrušit výběr" |
| `expandRow($key)` | Rozbalení/sbalení řádku |
| `toggleAllRowExpansion()` | Hromadné rozbalení/sbalení (`toggleFlattenMode()` je zastaralý alias) |
| `executeAction($name, $key)` | Kliknuto tlačítko akce |
| `executeBulkAction($name)` | Kliknuta hromadná akce |
| `updateCell($column, $key, $value)` | Potvrzena inline editace |
| `confirmActionExecution()` | Kliknuto „potvrdit" v modalu |
| `cancelAction()` | Kliknuto „zrušit" v modalu |
| `submitActionForm()` | Odeslán formulář akce |

---

## Konfigurační API tabulky

Třída `Table` poskytuje komplexní fluent API. Níže je kompletní reference.

### Zdroj dat

```php
// Z třídy Eloquent modelu (auto-vytvoří dotaz)
->model(string $modelClass)

// Vlastní základní dotaz (přepíše model)
->query(Builder $query)

// Upravit auto-generovaný dotaz
->modifyQueryUsing(Closure $fn)

// Sloupec primárního klíče (výchozí: 'id')
->primaryKey(string $column)
```

**Příklady:**

```php
// Jednoduchý model
$table->model(User::class);

// Vlastní dotaz s eager loady a scopy
$table->query(
    User::query()
        ->where('tenant_id', auth()->user()->tenant_id)
        ->withCount(['posts', 'comments'])
        ->with(['department', 'team'])
);

// Úprava auto-dotazu
$table->model(User::class)
      ->modifyQueryUsing(fn (Builder $q) => $q->where('active', true));

// UUID primární klíč
$table->model(Order::class)->primaryKey('uuid');
```

### Sloupce

```php
->columns(array $columns)
```

Všech 13 typů sloupců viz [Reference sloupců](columns/index.md).

### Filtry

```php
->filters(array $filters)
```

Všechny typy filtrů viz [Reference filtrů](filters/index.md).

### Akce

```php
// Řádkové akce (per záznam)
->actions(array $actions)

// Hromadné akce (pro vybrané záznamy)
->bulkActions(array $actions)

// Hlavičkové akce (na úrovni tabulky, bez kontextu záznamu)
->headerActions(array $actions)

// Pozice sloupce akcí
->actionsPosition(string 'start'|'end')     // výchozí: 'end'

// Zarovnání sloupce akcí
->actionsAlignment(string 'left'|'center'|'right')

// Popisek hlavičky sloupce akcí
->actionsColumnLabel(string $label)

// Pevná šířka sloupce akcí
->actionsColumnWidth(string $width)          // např. '120px'
```

Kompletní API akcí viz [Akce](../core/actions.md).

### Hledání

```php
// Zapnout globální hledání napříč všemi searchable sloupci
->searchable(bool $searchable = true)

// Nastavit, jak se zadaný výraz čte (viz níže)
->search(Closure|SearchConfig $config)
```

Ve výchozím stavu se celý výraz hledá jako jeden podřetězec napříč všemi
searchable sloupci, spojený přes OR: `LIKE '%výraz%'` na MySQL/MariaDB a SQLite,
`ILIKE` na PostgreSQL. Znaky `%` a `_`, které uživatel napíše, se escapují —
hledá se tedy po nich, místo aby fungovaly jako zástupné znaky.

### Syntaxe hledání

Každou schopnost zapínáte pro danou tabulku zvlášť — bez toho se nic
neinterpretuje, takže se stávajícímu hledání pod rukama nezmění chování.

```php
use NyonCode\WireCore\Core\Query\Search\SearchConfig;

$table->search(fn (SearchConfig $s) => $s
    ->tokenize()    // mezery znamenají AND, uvozovky drží frázi pohromadě
    ->ranges()      // >100, <=20, 10..20, 2026-01-01..2026-03-31
    ->wildcards()   // nov* najde novak
);
```

| Schopnost | Co uživatel napíše | Co to udělá |
| --- | --- | --- |
| `tokenize()` | `Ada Lovelace` | Každé slovo musí sedět, každé napříč všemi sloupci — takže se trefí i křestní jméno v jednom sloupci a příjmení v druhém. |
| `tokenize()` | `"Ada Lovelace"` | Fráze v uvozovkách zůstane jedním slovem a nikdy se nečte jako operátor. |
| `ranges()` | `>100`, `>=100`, `<10`, `<=10`, `=42` | Porovnává proti sloupcům, které drží číslo nebo datum. |
| `ranges()` | `10..20`, `10..`, `..20` | Uzavřený nebo jednostranně otevřený rozsah. |
| `ranges()` | `2026-01-01..2026-03-31`, `31.01.2026` | Totéž nad daty. |
| `ranges()` | `8866 01..08` | Rozsah uvnitř jedné řady strukturovaného kódu — viz níže. |
| `wildcards()` | `nov*`, `a?b` | `*` zastoupí libovolný počet znaků, `?` právě jeden. |
| `literal()` | — | Vypne všechno zpět (výchozí stav). |

Zadané datum se čte v té podrobnosti, v jaké bylo napsáno: `2026-01-31` znamená
celý ten den, `2026-01` celý měsíc a `2026` celý rok — takže `<=2026-01-31`
zahrne i záznam pořízený 31. v 23:30.

Porovnání se ptá jen sloupce, který na ně umí odpovědět. Typ hodnoty se odvodí
z castů modelu (`decimal:2`, `datetime`, …); tam, kde casty za sloupec mluvit
nemohou, ho deklarujte přes
[`Column::searchAs()`](columns/index.md#hledani). Porovnání, na které nemůže
odpovědět žádný sloupec — `>100` v tabulce jmen — se hledá jako doslovný text,
který uživatel napsal, místo aby tiše sedělo na všechno.

```php
// Na ">1000" umí odpovědět jen `amount`; slova výběr dál zúží.
$table->search(fn (SearchConfig $s) => $s->tokenize()->ranges());

// Uživatel napíše:  praha >1000
// Zůstanou řádky:   něco obsahuje "praha"  A ZÁROVEŇ  amount > 1000
```

### Rozsahy uvnitř strukturovaného kódu

Kód jako `8866 01`, `8866 02`, … má společnou řadu a končí číslem doplněným
nulami. Označte sloupec přes
[`searchAs('code')`](columns/index.md#hledani) a přes pořadové číslo lze rovnou
zadávat rozsah:

```php
$table
    ->searchable()
    ->search(fn (SearchConfig $s) => $s->tokenize()->ranges())
    ->columns([
        TextColumn::make('reference')->searchable()->searchAs('code'),
    ]);

// Uživatel napíše:  8866 01..08
// SQL:              reference BETWEEN '8866 01' AND '8866 08'
```

Potřeba jsou obě poloviny: `searchAs('code')` říká, co sloupec drží, `ranges()`
je to, co vůbec dovolí rozsah napsat, a `tokenize()` je to, co oddělí řadu od
pořadového čísla. Deklarace, na kterou se hledání nemůže zeptat, se při renderu
tabulky odmítne — s vypnutým `ranges()` by se `8866 01..08` hledalo jako
doslovný text a tabulka by se vrátila prázdná, aniž by to na obrazovce cokoli
vysvětlovalo.

Mezera uvnitř kódu je zároveň tím, co výraz dělí — `8866 01..08` tedy přijde
jako slovo `8866` a rozsah `01..08`. Rozsah si nese slovo, které mu přímo
předchází, a sloupec typu kód jím doplní obě meze — řadu tedy píšete jednou, ne
na obou stranách. Každý jiný sloupec to slovo ignoruje a `01..08` čte jako
obyčejný rozsah, takže `praha 10..20` na téže tabulce dál znamená „obsahuje praha
a částka mezi 10 a 20“. Jednostranná porovnání fungují stejně: `8866 >=09`.

Dvě pravidla, která to drží poctivé:

- **Číslo musí být uložené doplněné nulami a psát se tak, jak je uložené.**
  Porovnání textem je správně jen dokud je šířka konstantní (`01 … 08` se
  abecedně řadí stejně jako číselně, `9 … 10` už ne). Rozsah se porovnává v té
  šířce, v jaké byl napsaný — `1..8` proti uloženým `01 … 08` je tedy
  `BETWEEN '8866 1' AND '8866 8'` a porovnává se textem: celou doplněnou řadu
  mine a dosáhne místo toho na `8866 12`. Rozsah přes hranici šířky se doplní za
  vás — `8866 50..100` se čte jako `050..100`, protože stý člen může existovat
  jen v třímístné řadě.
- **Řada je jedno slovo před rozsahem.** `faktura 8866 01..08` hledá rozsah uvnitř
  `8866` a `faktura` musí sedet zvlášť; kód se dvěma mezerami je mimo dosah.

Hledání se s filtry kombinuje přes AND, při změně vrací stránkování na první
stranu a při zapnutém [`queryString()`](advanced.md#perzistence-stavu-v-url) se ukládá do URL.

### Řazení

```php
// Zapnout řazení kliknutím na hlavičku sloupce
->sortable(bool $sortable = true)

// Výchozí řazení při prvním načtení
->defaultSort(string $column, string $direction = 'asc')
```

Každý dotaz tabulky končí primárním klíčem jako rozhodčím kritériem, ve směru,
který už platí. Stránka je výřez z nějakého uspořádání — bez něj je ten výřez
nedefinovaný: dva řádky, které řazení považuje za shodné, se můžou vrátit
v libovolném pořadí, a na PostgreSQL, kde `UPDATE` zapíše řádek nově na konec
haldy, editace řádku na první stránce protlačí dosud nezobrazený záznam před
začátek druhé stránky. Rozhodčí kritérium se vynechá tam, kde klíč není
přípustný člen řazení: `GROUP BY`, `DISTINCT` a sjednocení.

### Stránkování

```php
// Zapnout stránkování
->paginated(bool $paginated = true)

// Výchozí počet na stránku — int, nebo 'all' pro jednu stránku se vším
->perPage(int|string $perPage = 10) // [tl! focus:start]

// Volby dropdownu počtu na stránku; velikostí smí být slovo 'all'
->perPageOptions(array $options = [10, 25, 50, 100]) // [tl! focus:end]

// Jednoduché stránkování — bez COUNT(*) dotazu, jen Předchozí/Další
->simplePagination()

// Kurzorové stránkování — bez offsetu, konstantní čas
->cursorPagination()

// Standardní stránkování (výchozí) — plná čísla stránek
->standardPagination()
```

**Kdy které použít:**

| Režim | Nejlepší pro | Kompromisy |
|------|----------|------------|
| Standardní | < 100k záznamů, uživatelé potřebují čísla stránek | COUNT(*) při každém načtení stránky |
| Jednoduché | 100k–1M záznamů, sekvenční procházení | Bez celkového počtu, bez čísel stránek |
| Kurzorové | > 1M záznamů, real-time data | Bez náhodného přístupu na stránku, neprůhledné kurzory |

`perPageOptions()` vždy nabídne i nakonfigurovaný `perPage()`, takže
`->perPage(3)` proti výchozím volbám vykreslí select, který `3` opravdu umí
zobrazit, místo aby si protiřečil s řádky na obrazovce. Hodnota per-page
přicházející od klienta, kterou tabulka nenabízí, spadne zpět na `perPage()`.

**Zobrazit vše na jedné stránce.** Velikostí stránky smí být slovo `'all'`,
které přidá poslední volbu bez jakéhokoli limitu:

```php
->perPageOptions([10, 25, 50, 'all'])
```

Řadí se vždy nakonec, ať byla deklarovaná kdekoli, a ukládá se jako celé číslo
`Table::PER_PAGE_ALL` — hodnota, kterou select posílá zpět, kterou nese query
string a kterou porovnává cache key, protože všechny tři pracují s velikostmi
stránky jako s inty. `->perPage('all')` z ní udělá výchozí nastavení tabulky.

`'all'` záměrně **není** mezi dodávanými volbami. Velikost stránky je jediná
věc, která stojí mezi tabulkou a načtením celého jejího zdroje do paměti, a
výše popsané spadnutí zpět existuje právě proto, aby si o to podvržený požadavek
nemohl říct — podstrčené `perPage: -1` spadne zpět na tabulce, která `'all'`
nikdy nenabídla. Napsat ho je způsob, jak tabulka řekne, že u *jejích* dat je
ten kompromis přijatelný. Žádný strop za tím není: dávej ho na tabulku, jejíž
počet řádků znáš, ne na tu nad zdrojem, který roste bez omezení.

**Stránky mimo rozsah se samy zakotví zpět.** Standardní stránkování ořízne na
poslední zaplněnou stránku vždy, když uložené číslo stránky ukazuje za konec
výsledků — sdílený odkaz `?page=5`, filtr, který množinu zmenšil, řádky smazané
někým jiným — takže neexistující stránka se nikdy nevykreslí jako prázdná
tabulka. Jednoduché a kurzorové stránkování nemá celkový počet, ke kterému by
šlo oříznout, a zůstává beze změny.

### Výběr (hromadné akce)

```php
// Zapnout sloupec s checkboxy pro výběr
->selectable(bool $selectable = true)
```

Když je zapnuto, objeví se checkboxy. Klíče vybraných záznamů jsou uloženy v `tableState.selection.records` (legacy alias `$selectedRecords`). Hromadné akce pracují s výběrem.

Výběr je spravován na straně klienta (Alpine) — zaškrtávání řádků, výběr všech
a lišta výběru reagují okamžitě bez roundtripu na server. Stav se synchronizuje
s dalším requestem, takže hromadné akce vždy vidí aktuální výběr; tabulky se
souhrnnou patičkou commitují změny výběru automaticky (debounced), aby součty
v rozsahu výběru zůstaly živé.

### Vzhled

```php
// Střídavé barvy řádků
->striped(bool $striped = true)

// Zvýraznění řádku při hoveru (výchozí: true)
->hoverable(bool $hoverable = true)

// Zmenšený padding buněk
->compact(bool $compact = true)

// Ohraničení tabulky/buněk
->bordered(bool $bordered = true)

// Vlastní CSS třída na elementu <table>
->tableClass(string $class)

// Vlastní CSS třída na <thead>
->headerClass(string $class)

// Vlastní CSS třída na <tr>, staticky nebo počítaná per záznam
->rowClass(string|Closure $class)

// Obarvení celého řádku sémantickou barvou, staticky nebo per záznam
->rowColor(string|Closure|null $color)
```

**Podmíněná barva řádku.** `rowColor()` obarví celý řádek stejnou sémantickou
paletou jako odznaky a všechny ostatní plochy (`success`, `warning`, `danger`,
`info`, `primary`, `gray` nebo libovolný raw Tailwind odstín). Vrácením `null`
z Closure zůstane řádek bez tónu. Obarvený řádek automaticky dostane hover ve
stejném odstínu a potlačí neutrální hover/zebrování, takže barva vždy vypadá
čistě:

```php
->rowColor(fn (Invoice $record) => match ($record->status) {
    'overdue' => 'danger',
    'pending' => 'warning',
    'paid'    => 'success',
    default   => null,
})
```

Preferuj `rowColor()` před ručně psanými background třídami — prochází
kanonickým vlastníkem `HasColor`, takže zůstává konzistentní se zbytkem UI a
funguje ve světlém i tmavém režimu. `rowClass()` použij, když potřebuješ
libovolné utility (tučné písmo, ring, průhlednost) místo tónu pozadí; obojí lze
kombinovat na téže tabulce:

```php
->rowColor(fn (Invoice $r) => $r->isOverdue() ? 'danger' : null)
->rowClass(fn (Invoice $r) => $r->isOverdue() ? 'font-semibold' : null)
```

### URL záznamu (klikatelné řádky)

```php
// Udělat celý řádek klikatelným
->recordUrl(string|Closure $url)
```

```php
// S Closure
->recordUrl(fn (User $record) => route('users.show', $record))
```

### Responzivní layout

```php
// Naskládat sloupce svisle na mobilu; 2. argument je breakpoint (výchozí 'md')
->stackedOnMobile(bool $stacked = true, string $breakpoint = 'md')   // 'sm','md','lg','xl'
->bulkMaxRecords(?int $max)                                          // kolik řádků smí načíst jedna hromadná akce (výchozí 1000, null = bez limitu)
->mobileCard(Closure $callback)                                      // pojmenuje titulek/podřádek/metriku/meta karty

// Sbalit akce řádku v mobilní kartě do jednoho rozbalovacího menu (od N akcí)
->collapseActionsOnMobile(bool $collapse = true, int $threshold = 3)

// Totéž pro akce hlavičky v toolbaru, pod mobileBreakpoint() tabulky
->collapseHeaderActionsOnMobile(bool $collapse = true, int $threshold = 2)
```

### Prázdný stav

```php
->emptyState(?string $heading = null, ?string $description = null, ?string $icon = null)
```

```php
$table->emptyState(
    heading: 'No users found',
    description: 'Try adjusting your filters or search term.',
    icon: 'users',
)
```

#### Akce prázdného stavu

Nabídněte z prázdného stavu cestu ven — obvykle „vytvořit první záznam":

```php
->emptyStateActions(array $actions)
```

```php
$table
    ->emptyState(
        heading: 'No posts yet',
        description: 'Write the first one.',
        icon: 'document-text',
    )
    ->emptyStateActions([
        Action::make('create')
            ->label('Create post')
            ->url(route('posts.create')),
    ]);
```

Přijímá se `Action` i `HeaderAction`. Prázdný stav nemá žádné řádky, takže jeho
akce běží bez záznamu — stejně jako akce v hlavičce, včetně modalu, formuláře
i potvrzení:

```php
->emptyStateActions([
    Action::make('create')
        ->label('Create post')
        ->form(fn () => Form::make()->schema([
            TextInput::make('title')->required(),
        ]))
        ->action(fn (array $data) => Post::create($data)),
])
```

Z absence záznamu plynou dvě věci:

- Vyhodnotí se jen **statická** `->url('/posts/create')`. Closure závislá na
  záznamu (`->url(fn ($record) => …)`) tu žádný záznam nedostane a zůstane
  nevyplněná, takže se akce vykreslí jako obyčejné tlačítko.
- Dejte akci prázdného stavu **vlastní jméno**. Když použijete jméno (nebo přímo
  objekt) akce z hlavičky, v prázdné tabulce se vykreslí obě — duplicitní
  `data-testid`, a pokud má akce `->keyboardShortcut()`, zaregistruje se window
  listener dvakrát a jeden stisk klávesy ji spustí dvakrát.

Tyto akce se nezobrazují, když tabulku vyprázdnil **filtr**: tam záznamy za
filtrem existují, takže prázdný stav místo toho nabízí filtr zrušit.

### Polling (auto-obnovení)

```php
// Zapnout polling v intervalu
->poll(string $interval = '5s')

// Pokračovat v pollingu, když je záložka prohlížeče skrytá
->pollKeepAlive(bool $keepAlive = true)

// Pollovat jen když je element viditelný ve viewportu
->pollOnlyVisible(bool $onlyVisible = true)

// Podmíněný polling
->pollWhen(Closure $condition)

// Livewire metoda k zavolání při pollu (výchozí: re-render)
->pollMethod(string $method)
```

```php
// Pollovat každých 5s, dokud jsou čekající joby
$table->poll('5s')
      ->pollWhen(fn () => Job::where('status', 'pending')->exists());
```

### Lazy loading

```php
// Odložit počáteční render tabulky
->lazy(bool $lazy = true)

// Placeholder HTML během načítání
->lazyPlaceholder(string $html)
```

```php
$table->lazy()
      ->lazyPlaceholder(
          '<div class="flex items-center justify-center p-12">
              <x-wire::icon name="refresh" class="w-8 h-8 animate-spin text-gray-400" />
          </div>'
      );
```

### Výkon

```php
// Cachovat výsledky dotazu
->cacheQuery(int $ttl, ?string $key = null)

// Zpracovat záznamy po chuncích (pro hromadné operace)
->chunk(int $size, Closure $callback)
```

```php
// Cache na 60 sekund — klíč auto-generovaný z hashe stavu
$table->cacheQuery(60);

// Vlastní cache klíč
$table->cacheQuery(300, 'users-table');
```

### Notifikace

```php
// Přepsat notifikační driver pro tuto tabulku
->notificationDriver(string $driver)
```

### Debugging

```php
// Získat objekt QueryPlan pro inspekci
->debugQueryPlan(): QueryPlan

// Získat raw SQL s dosazenými bindingy
->toSql(): string

// Získat analýzu metadat sloupců
->getColumnsInfo(): array
->getDatabaseColumns(): array
->getDatabaseColumnsInfo(): array
```

---

## Inline editace

Tři typy sloupců podporují inline editaci — buňky se stanou editovatelnými inputy, které validují a ukládají okamžitě:

| Typ sloupce | UI prvek | Ukládá při |
|-------------|------------|----------|
| `TextInputColumn` | `<input>` | Blur nebo Enter |
| `SelectColumn` | `<select>` | Změně |
| `ToggleColumn` | Přepínač | Kliknutí |

```php
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\ToggleColumn;

$table->columns([
    TextInputColumn::make('name')
        ->rules(['required', 'string', 'max:255'])
        ->saveOnBlur(),

    SelectColumn::make('status')
        ->options([
            'draft' => 'Draft',
            'review' => 'In Review',
            'published' => 'Published',
        ])
        ->rules(['required', 'in:draft,review,published']),

    ToggleColumn::make('is_featured')
        ->onColor('success')
        ->offColor('gray')
        ->disabled(fn ($record) => ! $record->is_published),
]);
```

### Životní cyklus inline editace

1. Uživatel upraví hodnotu buňky
2. Zavolá se `updateCell($column, $recordKey, $newValue)`
3. **Validace** proběhne proti pravidlům sloupce
4. **Událost `CellUpdating`** odeslána (lze naslouchat)
5. **Eloquent update** perzistuje novou hodnotu
6. **Událost `CellUpdated`** odeslána
7. Zobrazena úspěšná notifikace

Pokud validace selže, buňka se vrátí a zobrazí chybovou zprávu.

### Vlastní save logika

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->editableUsing(function (Model $record, string $column, mixed $value) {
        // Vlastní save logika
        $record->update([$column => Str::title($value)]);
        Cache::forget("user:{$record->id}");
    })
```

### Fill handle

`Table::fillHandle()` přidá editovatelným buňkám úchyt jako v Excelu: hodnotu
přetáhnete na řádky pod ní a celý rozsah se zapíše jedním requestem. Zapíná se
explicitně, jednotlivý sloupec vyloučíte přes `Column::fillable(false)`. Viz
[Fill handle](columns/fill-handle.md).

---

## Vzory z reálného světa

### Multi-tenant tabulka

```php
public function table(Table $table): Table
{
    return $table
        ->query(
            Order::query()->where('tenant_id', auth()->user()->tenant_id)
        )
        ->columns([...])
        ->filters([...]);
}
```

### Tabulka se složitými relacemi

```php
$table->model(Invoice::class)
      ->columns([
          TextColumn::make('number')->searchable(),
          TextColumn::make('client.company.name')  // vnořená relace
              ->label('Company')
              ->searchable(),
          TextColumn::make('items.sum.amount')      // agregát
              ->label('Total')
              ->money('CZK'),
          TextColumn::make('payments.count')        // count agregát
              ->label('Payments'),
          BadgeColumn::make('status')
              ->colors([...]),
      ]);
```

### Podmíněné akce

```php
$table->actions([
    Action::make('approve')
        ->icon('check')
        ->color('success')
        ->visible(fn ($record) => $record->status === 'pending')
        ->action(fn ($record) => $record->approve()),

    Action::make('edit')
        ->icon('pencil')
        ->disabled(fn ($record) => $record->is_locked)
        ->url(fn ($record) => route('invoices.edit', $record)),

    ActionGroup::make('more', [
        Action::make('duplicate')
            ->icon('copy')
            ->action(fn ($r) => $r->replicate()->save()),
        Action::make('pdf')
            ->icon('document')
            ->url(fn ($r) => route('invoices.pdf', $r), openInNewTab: true),
        Action::divider(),
        Action::make('delete')
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Invoice?')
            ->action(fn ($r) => $r->delete()),
    ]),
]);
```

### Dynamický počet na stránku se synchronizací do URL

Všechny vlastnosti stavu jsou Livewire-vázané, takže přetrvávají napříč načteními stránky přes query string (pokud je nakonfigurováno ve vaší Livewire komponentě):

```php
class UserTable extends Component
{
    use WithTable;

    // Přetrvat stav v URL
    protected $queryString = [
        'tableSearch' => ['except' => ''],
        'tableSortColumn' => ['except' => ''],
        'tableSortDirection' => ['except' => 'asc'],
        'tablePerPage' => ['except' => 10],
    ];
}
```

---

## Související dokumentace

| Dokument | Co pokrývá |
|----------|---------------|
| [Sloupce](columns/index.md) | Všech 13 typů sloupců — TextColumn, BadgeColumn, BooleanColumn, IconColumn, ImageColumn, ButtonColumn, ToggleColumn, SelectColumn, TextInputColumn, StackedColumn, SplitColumn, PollColumn |
| [Filtry](filters/index.md) | SelectFilter, DateFilter, NumberRangeFilter, TernaryFilter, vlastní filtry, filtry na úrovni sloupce |
| [Exporty](exports.md) | Exporty CSV, Excel a PDF pro aktuální dotaz tabulky |
| [Importy](imports.md) | Importy CSV — mapování hlaviček, přetypování, validace po řádcích, updateExisting |
| [Správci relací](relation-managers.md) | Tabulky zúžené na relaci jako samostatné Livewire komponenty |
| [Pokročilé](advanced.md) | Podřádky, souhrnná patička, polling, lazy loading, cachování, debug, responzivita |
| [Výběr řádků](selection.md) | Zaškrtávátka, „vybrat vše odpovídající" a výběrová gesta |
| [Akce nad záznamem](record-actions.md) | Vazby na klik, dvojklik, pravý klik a klávesy celého řádku |
| [Vrstva gest](gestures.md) | `gestures()` — opt-in klávesová/tažecí vrstva a fallback na tlačítka na mobilu |
| [Akce](../core/actions.md) | Kompletní systém akcí — modály, formuláře, wizard kroky, životní cyklus |
