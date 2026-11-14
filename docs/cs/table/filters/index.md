---
order: 30
summary: Všechny vestavěné filtry, operátory, které sdílejí, rozvržení panelu filtrů a tlačítko Použít, a kde mezi requesty žije stav filtrů.
---

# Filtry

Wire Table poskytuje **7 vestavěných typů filtrů** plus možnost postavit vlastní
filtry. Filtry žijí v panelu filtrů — za tlačítkem *Filtry* v toolbaru, nebo
rozložené nad řádky — a přetrvávají ve stavu Livewire přes `$tableFilters`. Tato
stránka pokrývá tok, operátory, které sdílí každý filtr, panel a sdílené API;
každý typ má svou vlastní stránku.

## Typy filtrů

| Filtr | Použití pro |
|--------|---------|
| [TextFilter](text.md) | Volný text — obsahuje, je, začíná na, jejich negace, je prázdné |
| [SelectFilter](select.md) | Jednoduchý/vícenásobný výběr z možností, enumů nebo vlastních hodnot sloupce, s počty |
| [DateFilter](date.md) | Jedno datum (před / po / dne), rozsah s předvolbami nebo měsíc + rok |
| [NumberRangeFilter](number-range.md) | Min/max číselný rozsah |
| [TernaryFilter](ternary.md) | Trojstavový boolean (vše / true / false) |
| [TrashedFilter](trashed.md) | Měkké mazání: živé / smazané / všechny záznamy |
| [QueryBuilderFilter](query-builder.md) | Podmínky, které uživatel spojí přes AND / OR, jedna úroveň skupin |

## Více

- [Filtry relací a podřádků](relationships.md) — filtrovat podle souvisejících modelů a hodnot podřádků
- [Filtry na úrovni sloupce](column-level.md) — řádek filtrů nebo trychtýř v hlavičce sloupce, počty, předvolby, „Jen tato hodnota“
- [Vlastní třída filtru](custom.md) — postavit vlastní komponentu filtru
- [Vzory a recepty](patterns.md) — kompletní příkladové lišty filtrů

## Tok filtru

```
Table::filters([...])
│
├── Render: Komponenty filtrů v sidebar/header liště
│   └── Každý filtr vykreslí svůj vlastní Blade pohled
│
├── Stav: pole $tableFilters ['name' => 'value', ...]
│   ├── {value, operator} u filtru s výběrem operátoru
│   └── Ukládá se do stavu Livewire komponenty
│
└── Apply: Když se stav změní (nebo na Použít, s ->deferFilters())
    ├── Zavolá se callback apply() nebo query() každého filtru
    ├── Podmínky přidány do Eloquent Builderu
    └── Tabulka se znovu dotáže s aplikovanými filtry
```

Aplikace filtrů teče přes Core pipe `ApplyFilters` v pipeline QueryExecutoru.

## Operátory

Filtr ve výchozím stavu porovnává jedním způsobem — textový filtr *obsahuje*,
select *je*. `->operators()` nechá volbu na uživateli: vedle hodnoty se objeví
výběr a stav filtru se stane `{value, operator}`. Jeden slovník, `FilterOperator`,
slouží každému filtru a každý case vlastní svůj popisek, to, zda potřebuje
hodnotu, a své SQL:

| `FilterOperator` | Hodnota | Popisek | SQL na sloupci |
|---|---|---|---|
| `Contains` | `contains` | obsahuje | `LIKE '%v%'` |
| `NotContains` | `not_contains` | neobsahuje | `IS NULL OR NOT LIKE '%v%'` |
| `Equals` | `equals` | je | `= v` |
| `NotEquals` | `not_equals` | není | `IS NULL OR != v` |
| `StartsWith` | `starts_with` | začíná na | `LIKE 'v%'` |
| `EndsWith` | `ends_with` | končí na | `LIKE '%v'` |
| `In` | `in` | je jedním z | `IN (…)` |
| `NotIn` | `not_in` | není žádným z | `IS NULL OR NOT IN (…)` |
| `GreaterThan` / `GreaterThanOrEqual` | `gt` / `gte` | je větší než / je alespoň | `>` / `>=` |
| `LessThan` / `LessThanOrEqual` | `lt` / `lte` | je menší než / je nejvýše | `<` / `<=` |
| `IsEmpty` | `is_empty` | je prázdné | `IS NULL` (text: `OR = ''`) |
| `IsNotEmpty` | `is_not_empty` | není prázdné | `IS NOT NULL` (text: `AND != ''`) |

Které z nich filtr může nabídnout, záleží na jeho typu — `->operators(true)`
nabídne všechny tyto, seznam nabídne ty vyjmenované a cokoli, co typ neumí
použít, se vypustí:

| Filtr | Operátory |
|---|---|
| `TextFilter` | obsahuje, neobsahuje, je, není, začíná na, končí na, je prázdné, není prázdné |
| `SelectFilter` | je jedním z, není žádným z, je prázdné, není prázdné |
| `DateFilter` (jedno datum) | je dne, není dne, je před, je nejpozději, je po, je nejdříve, je prázdné, není prázdné — pro porovnání slova pro data |
| `NumberRangeFilter`, `TernaryFilter`, `TrashedFilter`, rozsah dat nebo měsíc | žádné |

**Jak se vybere platný operátor:** ten, který uvádí stav, *pokud ho filtr
nabízí* — podvržená URL nebo zastaralý uložený pohled s jiným se ignoruje — pak
`->defaultOperator()`, pak vlastní porovnání filtru, pak první nabízený.

**Co sémantika zaručuje:**

- **Negace ponechají řádky s `NULL`.** *Neobsahuje gmail* zahrne zákazníky bez
  e-mailu; holé `NOT LIKE` by je vyřadilo.
- **Přes relaci negace znamená „žádný související záznam neodpovídá“** —
  `whereDoesntHave` s pozitivním porovnáním, nikdy `whereHas` s negativním, které
  by ponechalo zákazníka s jednou adresou na gmailu a jednou jinou. *Je prázdné*
  přes relaci znamená „nemá žádný související záznam s hodnotou“.
- **„Je prázdné“ nepotřebuje hodnotu.** Filtr nastavený na *je prázdné* nebo
  *není prázdné* je aktivní i s prázdným polem hodnoty a jeho chip zní
  `Telefon: je prázdné`.
- **U textu „je prázdné“ najde i `''`**; u čísel a dat je to jen `IS NULL`
  (PostgreSQL odmítne porovnat takové sloupce s řetězcem).
- **Jedna klauzule planneru, kde to jde.** *obsahuje*, *je*, *je jedním z* a
  porovnání se plánují přes `QueryPlanner` jako každý filtr; negace a kontroly
  prázdnoty omezí dotaz přes `apply()` filtru.

```php
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;

TextFilter::make('email')
    ->operators(['contains', 'not_contains', 'is_empty']) // [tl! focus:start]
    ->defaultOperator(FilterOperator::NotContains) // [tl! focus:end]

SelectFilter::make('status')
    ->options(['paid' => 'Paid', 'pending' => 'Pending'])
    ->multiple()
    ->operators()                      // je jedním z, není žádným z, je prázdné, není prázdné [tl! focus]
```

S `Table::queryString()` cestuje operátor vedle hodnoty pod stejným parametrem s
příponou `_op` — `?filter_email=gmail&filter_email_op=not_contains` — a u
hlavičkového filtru jako `col_<sloupec>_op`. Filtr sloupce dostane stejnou volbu
přes [`->filterOperators()`](column-level.md#operatory-na-sloupci).

## Panel filtrů

Tři nastavení tabulky rozhodují, jak se panel chová a kde sedí:

- **`->deferFilters()`** — prvky panelu se přestanou aplikovat při každé změně a
  vedle *Resetovat* se objeví tlačítko **Použít**: jeden dotaz pro „vyber tři
  věci“, ne tři. Prvky zapisují do konceptu — `tableState.filterDraft` místo
  `tableState.filters` — takže žádný jiný request (stránka, řazení, polling,
  akce řádku) nezúží řádky rozpracovanou volbou. *Použít*
  (`applyTableFilters()`) zkopíruje koncept do filtrů a vrátí tabulku na první
  stránku. Cokoli jiného, co filtry přepíše — *Resetovat*, odebrání chipu,
  uložený pohled — začne koncept znovu od nich. Hlavičkové filtry sloupců
  zůstávají živé.
- **`->filtersLayout()`** — `FiltersLayout::Dropdown` (výchozí) nechá panel za
  tlačítkem *Filtry* v toolbaru, na telefonu jako spodní sheet.
  `FiltersLayout::AboveContent` rozloží filtry nad řádky, vždy otevřené — vhodné
  pro seznam, který lidé neustále zužují. `FiltersLayout::AboveContentCollapsible`
  je totéž za přepínačem, zavřené, dokud není potřeba; otevřeno či zavřeno si
  pamatuje každý prohlížeč.
- **`->filtersFormColumns(int)`** — filtry v 1–4 sloupcích od breakpointu `sm`
  výš (na telefonu vždy jeden); dropdown se rozšíří, aby se vešly.
  [`QueryBuilderFilter`](query-builder.md) zabere vždy celý řádek.

Každé se určí nejprve voláním na tabulce, pak configem, pak výchozí hodnotou:

```php
// config/wire-table.php
'defaults' => [
    'filters_layout' => env('WIRE_TABLE_FILTERS_LAYOUT', 'dropdown'), // 'dropdown' | 'above' | 'above_collapsible'
    'defer_filters' => env('WIRE_TABLE_DEFER_FILTERS', false),
],
```

`filtersFormColumns()` nemá klíč v configu; výchozí je `1`.

```php
<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FiltersLayout;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

class ListInvoices extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('customer'),
                TextColumn::make('status'),
            ])
            ->filters([
                TextFilter::make('customer')->operators(),
                SelectFilter::make('status')->multiple()
                    ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
                    ->withCounts(),
                DateFilter::make('issued_at')->presets(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible) // [tl! focus:start]
            ->filtersFormColumns(3)
            ->deferFilters(); // [tl! focus:end]
    }

    public function render()
    {
        return view('livewire.list-invoices');
    }
}
```

Panel se otevře nad řádky ve třech sloupcích; nic se nedotazuje, dokud nepřijde
*Použít*.

---

## Sdílené API filtru

Každý filtr dědí ze základní třídy `Filter`.

### Factory a identita

```php
Filter::make(string $name)           // statická factory
->label(?string $label)               // zobrazovací popisek (automaticky generovaný z názvu)
->getName(): string
->getLabel(): string
```

### Vazba na sloupec

```php
->column(string $column)             // DB sloupec, na kterém filtrovat (výchozí $name)
->getColumn(): string
```

Když `column()` není zavoláno, filtr použije svůj `$name` jako databázový sloupec.

### Vlastní logika dotazu

```php
->query(Closure $fn)                 // vlastní callback dotazu
```

Signatura callbacku je `function (Builder $query, mixed $value): Builder` — **musí vrátit query builder** (runtime přiřadí `$query` návratové hodnotě callbacku).

```php
SelectFilter::make('activity_level')
    ->options([...])
    ->query(fn (Builder $query, string $value) => match ($value) {
        'active' => $query->where('last_active_at', '>=', now()->subDays(7)),
        'inactive' => $query->where('last_active_at', '<', now()->subDays(30)),
        'new' => $query->where('created_at', '>=', now()->subDays(7)),
        default => $query,
    })
```

#### Co obsahuje `$value`

Tvar závisí na typu filtru — rozsahový filtr předá pole, ternary předá skutečný
boolean:

| Filtr | `$value` | Příklad |
|-------|----------|---------|
| `TextFilter` | `string` — co uživatel napsal | `'faktura'` |
| `SelectFilter` | klíč option `string\|int` | `'active'` |
| `SelectFilter` + `->multiple()` | `array` klíčů options | `['active', 'pending']` |
| `TernaryFilter` | `bool` — `true` pro Ano, `false` pro Ne | `false` |
| `NumberRangeFilter` | `array{min, max}` — kterákoli strana může být `''` | `['min' => '10', 'max' => '']` |
| `DateFilter` | datum jako `string` | `'2026-07-23'` |
| `DateFilter` + `->range()` | `array{from, to}` | `['from' => '2026-01-01', 'to' => '']` |
| `DateFilter` + `->month()` | `string` `'YYYY-MM'` | `'2026-07'` |

Callback se volá jen když je filtr **aktivní** — prázdný stav (`null`, `''`,
`[]` nebo „Vše“ u ternary) filtr vypne a do closure se vůbec nedostane, takže se
proti němu nemusíte bránit. Výjimkou jsou v jednom směru vícepolní filtry:
rozsah zůstává aktivní, dokud je vyplněná *kterákoli* strana, takže si každou
mez ověřte zvlášť.

Třetí argument nese syrový odeslaný stav před normalizací — pro vzácné případy,
kdy callback potřebuje transportní podobu:

```php
->query(function (Builder $query, mixed $value, mixed $raw) { … })
```

### Viditelnost a oprávnění

```php
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->isHidden(): bool
->permission(string $permission)     // viditelné jen když má uživatel oprávnění
```

```php
DateFilter::make('deleted_at')
    ->range()
    ->permission('view-deleted-records')

SelectFilter::make('internal_status')
    ->options([...])
    ->visible(fn () => auth()->user()->is_admin)
```

### Výchozí hodnota

```php
->default(mixed $value)              // předvybráno při prvním načtení
->getDefault(): mixed
```

```php
SelectFilter::make('status')
    ->options([...])
    ->default('active')              // "active" předvybráno
```

### Výběr operátoru

```php
->operators(array|bool $operators = true)          // true = každý operátor, který typ podporuje, list<FilterOperator|string>, false = bez výběru
->defaultOperator(FilterOperator|string $operator)  // na čem nový filtr začne
->getOperators(): array                             // list<FilterOperator> na výběru — [] bez něj
->hasOperatorPicker(): bool                         // nabízí se víc než jeden operátor
->getDefaultOperator(): FilterOperator              // výchozí, omezený na to, co se nabízí
->getActiveOperator(): FilterOperator               // operátor platný pro aktuální stav
->operatorLabel(FilterOperator $operator): string   // formulace filtru — datumový filtr říká „je před“
```

Slovník a sémantiku popisuje oddíl [Operátory](#operatory) výše.

### Vícenásobný výběr

```php
->multiple(bool $multiple = true)
```

Když je zapnuto, filtr přijímá pole hodnot a aplikuje `whereIn()`.

### Přizpůsobení pohledu

Neexistuje fluent setter `->view()`. UI filtru přizpůsobte jedním ze dvou způsobů:

- **Per typ filtru** — přepište `render()` ve vlastní podtřídě `Filter` a nasměrujte ji na svůj Blade pohled (viz [Vlastní třída filtru](custom.md)).
- **Pro celý projekt** — publikujte pohledy balíčku a upravte partialy pod `resources/views/vendor/wire-table/tables/filters/` (`select`, `date`, `number-range`, `ternary`, `form-field`).

```bash
php artisan vendor:publish --tag=wire-table::views
```

### Pro autory filtrů

Háčky, které přepisuje [vlastní filtr](custom.md), a ty, které tabulka volá na
každém filtru. Sami je voláte zřídka:

```php
->supportedOperators(): array                      // list<FilterOperator>, které typ umí použít — [] v základním Filter
->operatorFromState(mixed $raw): FilterOperator    // operátor, který uvádí stav, pokud je nabízen; jinak výchozí
->forState(mixed $raw): static                     // kopie nesoucí operátor ze stavu — uložený filtr se nikdy nemění
->emptyState(): mixed                              // na co se filtr vyčistí: [] (multiple), null, nebo klíčovaný tvar jako {from, to}
->stateForCellValue(mixed $value, bool $exclude = false): mixed // hlavičkový stav, který zapíše „Jen / Vyloučit tuto hodnotu“; null = neumí to říct
->canExcludeCellValue(): bool                      // zda se nabízí „Vyloučit tuto hodnotu“ (na výběru je negativní operátor)
->builderInput(): string                           // prvek pro hodnotu v podmínce query builderu: 'text'|'select'|'date'|'boolean'
->spansFullWidth(): bool                           // zda panel dá filtru celý řádek mřížky
->deferred(bool $deferred = true)                  // nastavuje tabulka z Table::deferFilters()
->isDeferred(): bool
->wireModelDirective(int $debounce = 0): string    // 'wire:model' při odložení, jinak 'wire:model.live' (s debounce při > 0)
```

Vlastní filtr, který podporuje operátory, je vrátí ze `supportedOperators()` a ve
svém `apply()` čte `getActiveOperator()`; výběr, stav `{value, operator}`, URL
parametr `_op` i formulace chipu přijdou s tím.

---

## Indikátory filtrů

Aktivní filtry se vykreslí jako odstranitelné chipy pod toolbarem tabulky — každý
chip ukazuje čitelný popisek a tlačítko ×, které vyčistí jen daný filtr.
S více než jedním aktivním filtrem se vedle chipů objeví odkaz „Reset filters“.

Výchozí popisky se generují podle typu filtru:

| Filtr | Příklad chipu |
|---|---|
| `SelectFilter` | `Status: Active` (popisek option, ne surová hodnota) |
| `SelectFilter` + `multiple()` | `Status: Active, Trial` |
| `TernaryFilter` | `Verified: Yes` (popisky true/false) |
| `NumberRangeFilter` | `Price: 10 – 100`, `Price: ≥ 10`, `Price: ≤ 100` |
| `DateFilter` | `Created: 2026-06-11` |
| `DateFilter` + `range()` | `Created: 2026-06-01 – 2026-06-30` |
| `DateFilter` + `month()` | `Billed: May 2026` (přeložený název měsíce) |
| `DateFilter` + `presets()` | `Created: Last 7 days` (dokud je vybraná předvolba) |
| jakýkoli filtr s výběrem operátoru | `Email: does not contain gmail`, `Phone: is empty` |
| `QueryBuilderFilter` | `Customer contains Acme and Status is any of Paid` |
| základní `Filter` | `Label: value` |

### Přizpůsobení chipu

```php
SelectFilter::make('status')
    ->options([...])
    ->indicator('Only active customers')              // pevný popisek

DateFilter::make('created_at')
    ->indicator(fn ($value) => 'Since '.$value)       // closura: fn ($value, Filter $filter)
```

Vrácení `null` nebo prázdného řetězce z closury chip skryje, zatímco filtr zůstane
aplikovaný. Skryté/neautorizované filtry nikdy nevytvoří chipy.

### API komponenty

```php
$component->getActiveFilterIndicators();   // ['status' => 'Status: Active', ...]
$component->removeTableFilter('status');   // vyčistit jeden filtr (× tlačítko chipu)
$component->resetTableFilters();           // vyčistit všechny filtry + hledání
```

[Filtry hlavičky sloupce](column-level.md) tvoří stejné chipy vedle panelových
filtrů (jsou to také kanonické objekty `Filter`), s vlastními handlery:

```php
$component->getActiveColumnFilterIndicators();  // ['name' => 'Name: Widget', ...]
$component->removeColumnFilter('name');         // vyčistit jeden filtr sloupce
```

## Mobil

Lišta filtrů a menu přepínání sloupců se na telefonu otevřou jako bottom sheet.
Konfigurujte globálně přes blok `wire-core.mobile`, nebo u jednotlivých komponent pomocí
`->sheetOnMobile()` / `->mobileBreakpoint()` — viz
[mobilní prezentace](../../start/configuration.md#mobil).
