---
order: 31
summary: Jedno datum s před / po / dne, rozsah s pojmenovanými předvolbami, které zůstávají relativní, nebo měsíc — aplikované na sloupec nebo vlastní dotaz.
---

# DateFilter

Filtr data se třemi režimy: jedno datum (volitelně s výběrem *je před*, *je po*,
*je dne*…), rozsah od/do (volitelně s pojmenovanými předvolbami jako *Posledních
7 dní*) nebo celý měsíc.

```php
use NyonCode\WireTable\Filters\DateFilter;
```

## Jak to funguje

**Každé porovnání je po dnech.** Filtr nikdy neporovnává uloženou hodnotu přímo:
používá `whereDate` (nebo `whereYear` + `whereMonth` pro měsíc), takže na sloupci
datetime *je dne 3. března* najde 3. března ve 14:00. Proto také datumový filtr
nikdy neplánuje `QueryPlanner` — každý režim běží přes vlastní `apply()`.

**Režim určuje tvar stavu:**

| Režim | Stav | Operátory |
|---|---|---|
| jedno datum | `'2026-03-03'`, nebo `{value, operator}` s výběrem | je dne, není dne, je před, je nejpozději, je po, je nejdříve, je prázdné, není prázdné |
| `->range()` | `{from, to}` | žádné — porovnáním jsou dvě meze |
| `->range()` + `->presets()` | `{from, to, preset}` | žádné |
| `->month()` | `'2026-03'` | žádné |

`->operators()` u rozsahu nebo měsíce nenabídne nic — tyto režimy nemají z čeho
vybírat, ať jsou volání v jakémkoli pořadí. *není dne* ponechá řádky bez data; *je prázdné* je `IS NULL`.

**Předvolby se vyhodnotí při spuštění dotazu.** Předvolba se ukládá **názvem**
(`last_7_days`), nikdy daty, která pokrývala v den výběru. Uložený pohled nebo
sdílená URL s *Posledních 7 dní* znamená posledních sedm dní i příští týden; dvě
uložená data by se potichu stala pevným týdnem v minulosti. Data se počítají při
každém dotazu, s „dneškem“ čteným v `->timezone()` (výchozí: `app.timezone`).
Týden začíná pondělím. Když je předvolba nastavená, vyhrává nad od/do vedle ní a
tabulka před každým vykreslením zapíše do od/do dnešní data, takže picker ukazuje
období, které název právě znamená. Ruční výběr dnů název vymaže.

**Ovládání jsou pickery z formulářů.** Jedno datum kreslí `DateTimePicker`
z formulářů, rozsah `DateRangePicker` — jedno pole, dva měsíce vedle sebe
s předvolbami po straně; na telefonu oba pickery s předvolbami jako čipy —
v hlavičce, v popoveru sloupce i v panelu filtrů (`dateControl()` /
`rangeControl()`). Jen `month()` si ponechá měsíční vstup prohlížeče.

**Výchozí stav.** Jedno datum, bez výběru operátoru, bez předvoleb.
`->presets()` sám zapne režim rozsahu.

## Jedno datum

Výchozí režim vykreslí jeden date input a shoduje se s tím přesným dnem.

```php
DateFilter::make('created_at')
// Aplikuje: WHERE DATE(created_at) = '2024-01-15'
```

## Před, po, dne

```php
DateFilter::make('due_at')
    ->operators()                                // všechny operátory jednoho data
```

Výběr používá slova pro data, ne pro čísla: *je před* místo *je menší než*, *je
nejdříve* místo *je alespoň*. Chip zní stejně — `Splatnost: je před 2026-03-01`.
Užší sada:

```php
use NyonCode\WireTable\Enums\FilterOperator;

DateFilter::make('due_at')
    ->operators([FilterOperator::LessThan, FilterOperator::GreaterThan, FilterOperator::IsEmpty])
    ->defaultOperator(FilterOperator::LessThan)  // nový filtr se ptá „je před“
```

## Rozsah dat

Zavolejte `range()` pro výběr období přes `DateRangePicker` z formulářů („from“
a „to“). Kterákoli strana může zůstat prázdná, takže otevřené rozsahy fungují bez
extra konfigurace.

```php
DateFilter::make('created_at')
    ->range()
// Vykreslí DateRangePicker.
// Oba nastavené:  WHERE DATE(created_at) >= from AND <= to
// Jen from:       WHERE DATE(created_at) >= from
// Jen to:         WHERE DATE(created_at) <= to
```

## Předvolby

```php
DateFilter::make('created_at')
    ->presets()                                  // všechny předvolby; zapne range()
```

Vedle obou dat se objeví výběr pojmenovaných rozsahů. Nabídněte jen některé, jako
case `DatePreset` nebo jejich hodnoty:

```php
use NyonCode\WireTable\Enums\DatePreset;

DateFilter::make('created_at')
    ->presets([DatePreset::Today, DatePreset::Last7Days, DatePreset::ThisMonth, 'last_month'])
    ->timezone('Europe/Prague')                  // „dnes“ pro lidi, kteří seznam používají
```

| `DatePreset` | Hodnota | Pokrývá (včetně) |
|---|---|---|
| `Today` | `today` | dnes |
| `Yesterday` | `yesterday` | včera |
| `Last7Days` | `last_7_days` | dnešek a šest dní před ním |
| `Last30Days` | `last_30_days` | dnešek a 29 dní před ním |
| `ThisWeek` | `this_week` | pondělí – neděle tohoto týdne |
| `LastWeek` | `last_week` | pondělí – neděle minulého týdne |
| `ThisMonth` | `this_month` | první až poslední den tohoto měsíce |
| `LastMonth` | `last_month` | celý předchozí měsíc |
| `ThisQuarter` | `this_quarter` | celé aktuální čtvrtletí |
| `ThisYear` | `this_year` | 1. ledna – 31. prosince |
| `LastYear` | `last_year` | celý předchozí rok |

S `Table::queryString()` cestuje předvolba jako vlastní parametr s příponou
`_preset` (`?filter_created_at_preset=last_7_days`), vedle `_from` a `_to`. Chip
vypíše popisek předvolby — `Vytvořeno: Posledních 7 dní`. Neznámý nebo už
nenabízený název předvolby ve stavu se ignoruje.

## Vlastní popisky

V režimu rozsahu se popisky použijí jako placeholdery inputů.

```php
DateFilter::make('period')
    ->column('created_at')
    ->range()
    ->fromLabel('Created after')
    ->toLabel('Created before')
```

## Měsíc + rok

Zavolejte `month()` pro filtrování podle celého měsíce místo přesného dne. Vykreslí
nativní výběr měsíce (`<input type="month">`) a shoduje se s každým záznamem ve
vybraném měsíci:

```php
DateFilter::make('billed_at')
    ->month()
// Hodnota "2026-06" aplikuje: WHERE YEAR(billed_at) = 2026 AND MONTH(billed_at) = 6
```

Zkombinujte s [`subRows()`](relationships.md#filtrovani-podle-hodnot-podradku) pro filtrování rodičů podle
měsíce jejich dětských záznamů.

## Omezení data

```php
DateFilter::make('birth_date')
    ->minDate('1900-01-01')
    ->maxDate(now())
```

## Chování rozsahu

| from | to | Podmínka |
|------|----|-----------|
| nastaveno | null | `WHERE DATE(column) >= from` |
| null | nastaveno | `WHERE DATE(column) <= to` |
| nastaveno | nastaveno | `WHERE DATE(column) >= from AND <= to` |
| null | null | Žádný filtr neaplikován |

Předvolba, když je nastavená, dodá obě meze a vyhrává nad zadanými daty.

## Rozšířený příklad

Seznam faktur: *Vystaveno* odpoví na „minulý měsíc“ a v uložené URL zůstane
relativní, *Splatnost* odpoví na „před koncem čtvrtletí“, *Zaplaceno* na „nikdy“.

```php
<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\DatePreset;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\DateFilter;
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
                TextColumn::make('issued_at')->date(),
                TextColumn::make('due_at')->date(),
                TextColumn::make('paid_at')->dateTime(),
            ])
            ->filters([
                DateFilter::make('issued_at') // [tl! focus:start]
                    ->label('Issued')
                    ->presets([DatePreset::ThisMonth, DatePreset::LastMonth, DatePreset::ThisQuarter])
                    ->timezone('Europe/Prague'),
                DateFilter::make('due_at')
                    ->label('Due')
                    ->operators([FilterOperator::LessThan, FilterOperator::GreaterThan])
                    ->defaultOperator(FilterOperator::LessThan),
                DateFilter::make('paid_at')
                    ->label('Paid')
                    ->operators([FilterOperator::Equals, FilterOperator::IsEmpty]), // [tl! focus:end]
            ])
            ->queryString();
    }

    public function render()
    {
        return view('livewire.list-invoices');
    }
}
```

## API DateFilter

```php
->range(bool $range = true)         // období od/do (DateRangePicker) místo jednoho data
->month(bool $month = true)         // výběr měsíce, shoduje se s celým měsícem
->fromLabel(?string $label)         // placeholder "from" (výchozí: 'From')
->toLabel(?string $label)           // placeholder "to" (výchozí: 'To')
->minDate(string|DateTimeInterface|null $date)   // min volitelné datum
->maxDate(string|DateTimeInterface|null $date)   // max volitelné datum
->presets(array|bool $presets = true) // pojmenované rozsahy vedle dat: true = všechny, nebo list<DatePreset|string>; zapne range()
->timezone(?string $timezone)       // časová zóna, ve které se čte „dnes“ předvolby — výchozí app.timezone
->operators(array|bool $operators = true) // zděděno — jen jedno datum: 'equals'|'not_equals'|'lt'|'lte'|'gt'|'gte'|'is_empty'|'is_not_empty'
->defaultOperator(FilterOperator|string $operator) // zděděno — výchozí 'equals' (je dne)
->getPresets(): array               // list<DatePreset>
->hasPresets(): bool
->isRange(): bool
->isMonth(): bool
```

## Související

- [Filtry](index.md#operatory) — slovník operátorů, který sdílí každý filtr
- [Filtry na úrovni sloupce](column-level.md#predvolby-data) — `->filterPresets()` na sloupci
- [QueryBuilderFilter](query-builder.md) — jedno datum jako jedna podmínka z několika
