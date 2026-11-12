---
order: 33
summary: Filtr v hlavičce sloupce — řádek otevřených filtrů nebo trychtýř u každého sloupce — s operátory, počty, seznamy hodnot, předvolbami dat a „Jen tato hodnota“ po kliknutí pravým tlačítkem.
---

# Filtry na úrovni sloupce

Kromě dedikovaných komponent filtrů může mít jakýkoli sloupec inline filtr přímo ve své hlavičkové buňce. Viz [Sloupce — Filtrování na úrovni sloupce](../columns/editing.md#filtrovani-na-urovni-sloupce).

```php
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Admin', 'editor' => 'Editor']) // vyber více → whereIn

TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)

TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()
```

Filtry sloupců používají Livewire vlastnost `$columnFilters` (oddělenou od `$tableFilters`).

## Jeden filtrovací engine

Hlavičkový filtr sloupce je **umístění** téhož kanonického objektu `Filter`, který používá i dedikovaný [panel filtrů](index.md) — ne samostatný engine. Pomocné metody `filterAs*()` jsou tenké factory nad `TextFilter`, `SelectFilter`, `DateFilter`, `NumberRangeFilter` a `TernaryFilter`; sloupec vlastní *kde* se ovládací prvek vykreslí (hlavičková buňka) a *který atribut* cílí, zatímco `Filter` vlastní *jak* se aplikuje, vykresluje a persistuje. Díky tomu se každý filtr sloupce plánuje stejným `QueryPlanner`em jako panel filtr (joiny + kvalifikace vyřešeny jednou) a zdarma dědí autorizaci (`->can()` / `->visible()`).

Když potřebujete plnou kontrolu, můžete předat hotový filtr přes `->filter()`:

```php
use NyonCode\WireTable\Filters\SelectFilter;

TextColumn::make('status')->filter(
    SelectFilter::make('status')
        ->options(Status::class)
        ->searchable()
);
```

Filtry, jejichž SQL planner nedokáže vyjádřit jednou klauzulí — datum (`whereDate`), boolean (`= false OR IS NULL`) a operátory, které ponechávají řádky s `NULL` (*neobsahuje*, *není žádným z*) — se transparentně vrátí ke sdílenému `Filter::apply()`, přesně jako v panelu.

Hlavičkové filtry se vždy aplikují živě. `Table::deferFilters()` zdržuje jen
[panel filtrů](index.md#panel-filtru).

## Indikátorové chipy a sdílitelné URL

Protože hlavičkové filtry jsou kanonické objekty `Filter`, sdílí UX aktivních filtrů s panelem:

- **Indikátorové chipy** — aktivní filtr sloupce zobrazí odstranitelný chip v toolbaru vedle chipů panelových filtrů. Popisek chipu pochází z `Filter::getIndicator()` (popisky možností, meze rozsahu, formátovaná data); tlačítko × smaže jen daný filtr sloupce a odkaz „resetovat vše“ smaže všechny aktivní filtry.
- **Query-string persistence** — s `Table::queryString()` se filtry sloupců zapisují do URL pod parametrem `col_<sloupec>` (rozsahy používají `col_<sloupec>_min` / `_max` atd.), takže sdílená nebo znovunačtená URL reprodukuje stejný pohled. Relační (tečkové) názvy sloupců se přeskakují, stejně jako u panelových filtrů.

## Dvě rozvržení

Kde se hlavičkové filtry vykreslí, rozhoduje tabulka, v tomto pořadí:
`Table::columnFilterLayout()`, pak `wire-table.defaults.column_filter_layout`,
pak `'row'`.

- **`ColumnFilterLayout::Row`** (výchozí) — druhý řádek hlavičky se všemi filtry
  otevřenými najednou, každý v buňce široké jako jeho sloupec. Rychlé na široké
  obrazovce s pár filtry; stísněné u úzkého sloupce a telefon posouvá filtry do
  strany spolu s daty.
- **`ColumnFilterLayout::Popover`** — hlavička zůstane vysoká jeden řádek.
  Filtrovatelný sloupec nese vedle popisku trychtýř, **vyplněný, dokud je sloupec
  filtrovaný**, takže zúžená tabulka říká, *kde* je zúžená. Trychtýř otevře ten
  jeden filtr — výběr operátoru, hodnotu, *Vymazat*, *Hotovo* — v panelu
  velikosti filtru, ne sloupce; na telefonu je to spodní sheet s úchytem.

```php
use NyonCode\WireTable\Enums\ColumnFilterLayout;

$table->columnFilterLayout(ColumnFilterLayout::Popover)   // nebo 'popover'
```

```php
// config/wire-table.php
'defaults' => [
    'column_filter_layout' => env('WIRE_TABLE_COLUMN_FILTER_LAYOUT', 'row'), // 'row' | 'popover'
],
```

Obě rozvržení vykreslí stejný prvek navázaný na stejný stav
`columnFilters.<sloupec>`, takže chipy, URL a uložené pohledy nepoznají, které z
nich ho vykreslilo.

## Operátory na sloupci

`->filterOperators()` dá filtru sloupce stejný výběr operátoru, jaký dostane
filtr panelu ([Filtry → Operátory](index.md#operatory)). Volejte ho **po**
`filterAs*()`, které filtr vybírá — na sloupci, který filtr ještě nemá, nastaví
textový filtr:

```php
use NyonCode\WireTable\Enums\FilterOperator;

TextColumn::make('email')
    ->filterable()
    ->filterOperators(['contains', 'not_contains', 'ends_with', 'is_empty', 'is_not_empty'])

BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Administrator', 'editor' => 'Editor'])
    ->filterOperators([FilterOperator::In, FilterOperator::NotIn])
```

S výběrem se stav sloupce stane `{value, operator}` a URL nese operátor jako
`col_<sloupec>_op`.

## Počty a seznamy hodnot

```php
BadgeColumn::make('role')
    ->filterAsMultiSelect(['admin' => 'Administrator', 'editor' => 'Editor'])
    ->filterWithCounts()                   // Administrator (4), Editor (11)

TextColumn::make('city')
    ->filterAsValueList(limit: 200)        // vlastní hodnoty sloupce, spočítané, vyhledávatelné
```

`->filterWithCounts()` zapne [`SelectFilter::withCounts()`](select.md#jak-to-funguje)
pro select filtr sloupce (volejte ho po `filterAsSelect()` /
`filterAsMultiSelect()`; u jiného filtru nedělá nic). `->filterAsValueList()` je
*set filter* tabulkového procesoru: vyhledávatelný multi-select, jehož možnosti
jsou odlišné hodnoty sloupce, nejčastější první, omezené na `$limit`, každá se
svým počtem, pokud není `withCounts: false`.

Počty se berou pod všemi ostatními filtry a vyhledáváním, nikdy pod vlastní
hodnotou sloupce. **Sloupec, který u některých záznamů skrývá buňku přes
`visibleForRecord()`, se nikdy nepočítá a nikdy nevypíše své hodnoty** — seznam
by vypsal to, co ty buňky skrývají. Nepočítají se ani relační (tečkové) sloupce a
vlastní datové zdroje.

## Předvolby data

```php
TextColumn::make('created_at')
    ->date()
    ->filterPresets()                      // filtr rozsahu dat se všemi předvolbami
```

`->filterPresets()` udělá z filtru sloupce rozsah dat, pokud jím ještě není, a pak
nabídne [`DateFilter::presets()`](date.md#predvolby) — `true` pro všechny, nebo
seznam case či hodnot `DatePreset`. Předvolba se ukládá názvem, takže uložené
*Posledních 7 dní* zůstane relativní; URL nese `col_<sloupec>_preset`.

## Filtr z buňky

`Table::cellFilterMenu()` dovolí kliknutím pravým tlačítkem na buňku nastavit
hlavičkový filtr sloupce podle hodnoty v ní:

- **Jen tato hodnota** — ponechá záznamy s přesně touto hodnotou;
- **Vyloučit tuto hodnotu** — vyřadí je. Nabízí se jen tehdy, když má filtr na
  výběru negativní operátor (*není* pro jednu hodnotu, *není žádným z* pro
  multi-select).

```php
$table->cellFilterMenu()
```

Co který filtr zapíše:

| Filtr sloupce | Jen tato hodnota | Vyloučit tuto hodnotu |
|---|---|---|
| text, bez výběru | hodnotu (vlastní porovnání filtru, výchozí *obsahuje*) | — |
| text s *je* / *není* na výběru | *je* hodnota | *není* hodnota |
| multi-select (s *je jedním z* / *není žádným z*) | *je jedním z* [hodnota] | *není žádným z* [hodnota] |
| datum | ten den (rozsah od něj do něj) | — |
| rozsah čísel | min = max = hodnota | — |
| boolean | ano / ne | — |

**Hodnota se čte ze záznamu na serveru** (`filterByCellValue()`), nikdy ze
stránky: buňka ukazuje formátovanou hodnotu (*12. 3. 2026*, *Administrator*) a
filtr porovnává to, co je uložené. Buňka, kterou `visibleForRecord()` skrývá, se
ignoruje, a řádek, který má vlastní
[kontextové menu řádku](../advanced.md#kontextove-menu-radku), si ponechá to.

Select odpoví operátorem *je jedním z* / *není žádným z* — to je přesná shoda,
kterou nabízí. Filtr, jehož výběr přesnou shodu vůbec nemá — textový filtr s
nabídkou jen *obsahuje* a *začíná na* —, neumí říct „jen tato hodnota“, takže
jeho sloupec menu nad buňkou nedostane.

## Rozšířený příklad

Seznam uživatelů z náhledu ve workbenchi: trychtýře místo řádku filtrů, operátory
na textových sloupcích, počty u role, předvolby u data registrace a menu buňky.

```php
<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Component;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\ColumnFilterLayout;
use NyonCode\WireTable\Table;

class ListUsers extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(User::class)
            ->columnFilterLayout(ColumnFilterLayout::Popover) // [tl! focus:start]
            ->cellFilterMenu() // [tl! focus:end]
            ->columns([
                TextColumn::make('name')->sortable()
                    ->filterable()
                    ->filterOperators(), // [tl! focus]
                TextColumn::make('email')
                    ->filterable()
                    ->filterOperators(['contains', 'not_contains', 'ends_with', 'is_empty', 'is_not_empty']), // [tl! focus]
                BadgeColumn::make('role')
                    ->filterAsMultiSelect([
                        'admin' => 'Administrator',
                        'manager' => 'Manager',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ], 'Any role')
                    ->filterOperators() // [tl! focus:start]
                    ->filterWithCounts(), // [tl! focus:end]
                BooleanColumn::make('is_active')
                    ->filterAsBoolean('Active', 'Inactive'),
                TextColumn::make('created_at')->label('Joined')
                    ->date()
                    ->filterPresets(), // [tl! focus]
            ])
            ->queryString();
    }

    public function render()
    {
        return view('livewire.list-users');
    }
}
```

Klikněte pravým tlačítkem na *Editor* ve sloupci role a zvolte *Vyloučit tuto
hodnotu*: trychtýř role se vyplní, chip zní `Role: není žádným z Editor`, URL
získá `col_role_op=not_in` vedle hodnoty a počty u ostatních rolí zůstanou, jak
byly.

## Metody sloupce a tabulky

```php
// Na sloupci (CanBeFiltered)
->filterOperators(array|bool $operators = true)   // true = všechny operátory, které typ filtru podporuje, nebo list<FilterOperator|string>
->filterWithCounts(bool $condition = true)         // počet záznamů u každé možnosti select filtru sloupce
->filterAsValueList(int $limit = 100, bool $withCounts = true) // vyhledávatelný multi-select nad odlišnými hodnotami sloupce
->filterPresets(array|bool $presets = true)        // filtr rozsahu dat s pojmenovanými předvolbami: true = všechny, nebo list<DatePreset|string>

// Na tabulce
->columnFilterLayout(ColumnFilterLayout|string $layout) // 'row' (výchozí, config wire-table.defaults.column_filter_layout) | 'popover'
->cellFilterMenu(bool $enabled = true)             // pravé tlačítko na buňce: „Jen tato hodnota“ / „Vyloučit tuto hodnotu“ — výchozí false
```

## Související

- [Filtry](index.md) — operátory, panel filtrů a sdílené API filtrů
- [SelectFilter](select.md) — počty a možnosti načtené z dat
- [DateFilter](date.md) — předvolby a operátory data
- [Sloupce — Filtrování na úrovni sloupce](../columns/editing.md#filtrovani-na-urovni-sloupce) — pomocné metody `filterAs*()`
