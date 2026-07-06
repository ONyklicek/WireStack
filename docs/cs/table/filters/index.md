---
order: 30
---

# Filtry

Wire Table poskytuje **4 vestavěné typy filtrů** plus možnost postavit vlastní
filtry. Filtry žijí v liště filtrů nad tabulkou a přetrvávají ve stavu Livewire
přes `$tableFilters`. Tato stránka pokrývá tok a sdílené API; každý typ má svou
vlastní stránku.

## Typy filtrů

| Filtr | Použití pro |
|--------|---------|
| [SelectFilter](select.md) | Jednoduchý/vícenásobný výběr z options, relací nebo enumů |
| [DateFilter](date.md) | Jedno datum, rozsah dat nebo měsíc + rok |
| [NumberRangeFilter](number-range.md) | Min/max číselný rozsah |
| [TernaryFilter](ternary.md) | Trojstavový boolean (vše / true / false) |

## Více

- [Filtry relací a podřádků](relationships.md) — filtrovat podle souvisejících modelů a hodnot podřádků
- [Filtry na úrovni sloupce](column-level.md) — inline inputy filtru v hlavičce sloupce
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
│   └── Perzistováno ve stavu Livewire komponenty
│
└── Apply: Když se stav změní
    ├── Zavolá se callback apply() nebo query() každého filtru
    ├── Podmínky přidány do Eloquent Builderu
    └── Tabulka se znovu dotáže s aplikovanými filtry
```

Aplikace filtrů teče přes Core pipe `ApplyFilters` v pipeline QueryExecutoru.

---

## Sdílené API filtru

Každý filtr dědí ze základní třídy `Filter`.

### Factory a identita

```php
Filter::make(string $name)           // statická factory
->label(?string $label)               // zobrazovací popisek (auto-generovaný z názvu)
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

---

## Indikátory filtrů

Aktivní filtry se vykreslí jako odstranitelné chipy pod toolbarem tabulky — každý
chip ukazuje čitelný popisek a tlačítko ×, které vyčistí jen daný filtr.
S více než jedním aktivním filtrem se vedle chipů objeví odkaz „Reset filters".

Výchozí popisky se generují per typ filtru:

| Filtr | Příklad chipu |
|---|---|
| `SelectFilter` | `Status: Active` (popisek option, ne surová hodnota) |
| `SelectFilter` + `multiple()` | `Status: Active, Trial` |
| `TernaryFilter` | `Verified: Yes` (labely true/false) |
| `NumberRangeFilter` | `Price: 10 – 100`, `Price: ≥ 10`, `Price: ≤ 100` |
| `DateFilter` | `Created: 2026-06-11` |
| `DateFilter` + `range()` | `Created: 2026-06-01 – 2026-06-30` |
| `DateFilter` + `month()` | `Billed: May 2026` (přeložený název měsíce) |
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

## Mobil

Lišta filtrů a menu přepínání sloupců se na telefonu otevřou jako bottom sheet.
Konfigurujte globálně přes blok `wire-core.mobile`, nebo per komponenta pomocí
`->sheetOnMobile()` / `->mobileBreakpoint()` — viz
[mobilní prezentace](../../configuration.md#mobile).
