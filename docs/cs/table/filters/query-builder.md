---
order: 31
summary: Podmínky spojené přes „všechny“ / „kterákoli“, jedna úroveň skupin a každá podmínka je obyčejný znovu použitý filtr — pokročilý filtr pro ty, kdo si otázku skládají sami.
---

# QueryBuilderFilter

Filtr, který si uživatel sestaví: seznam podmínek — *Zákazník obsahuje Acme*,
*Stav je jedním z Zaplaceno*, *Vystaveno je před 1. březnem* — spojených přes
**všechny** (AND) nebo **kterákoli** (OR), s jednou úrovní skupin pro „všechny
tyto a kterákoli z těchto“. Sáhněte po něm, když jsou otázky, které lidé seznamu
kladou, příliš různorodé na to, aby je pokryla pevná řada filtrů.

```php
use NyonCode\WireTable\Filters\QueryBuilderFilter;
```

## Jak to funguje

**Nevlastní žádné porovnání.** Každé pole, o kterém podmínka může být, je
obyčejný filtr předaný do `->constraints()` — `TextFilter`, `SelectFilter`,
`DateFilter` s jedním datem, `TernaryFilter`. Podmínka jsou tři věci: který z
těch filtrů (*pole*), který z jeho [operátorů](index.md#operatory) a hodnota.
Builder je jen uspořádá; porovnání je vlastní kód filtru. Proto *neobsahuje*
ponechá řádky s `NULL`, *je prázdné* u textu najde i `''`, tečkový název
překročí relaci přes `whereHas` / `whereDoesntHave` a chip formuluje podmínku
přesně jako hlavičkový filtr — protože to dělá tentýž kód.

**Co `->constraints()` udělá s filtry, které dostane:**

1. Z `SelectFilter` udělá `->multiple()`, takže jeho podmínka zní *je jedním z*.
2. Filtr bez seznamu operátorů dostane `->operators()` — všechny operátory,
   které jeho typ podporuje. Seznam, který nastavíte sami, zůstane.
3. Jeho cesta ve stavu se přesune pod builder (`tableState.filters.<builder>`).

Filtry se mění na místě — pro builder vytvořte nové instance, nepředávejte
stejný objekt, který máte i v `Table::filters()`.

**Stav** je obyčejné pole `{combinator, rules}`. Pravidlo je
`{field, operator, value}`; skupina je další `{combinator, rules}`:

```php
[
    'combinator' => 'and',
    'rules' => [
        ['field' => 'status', 'operator' => 'in', 'value' => ['paid']],
        ['combinator' => 'or', 'rules' => [
            ['field' => 'customer', 'operator' => 'contains', 'value' => 'Acme'],
            ['field' => 'customer', 'operator' => 'contains', 'value' => 'Beta'],
        ]],
    ],
]
```

Každé čtení ho normalizuje: jiný combinator než `'or'` je `'and'`, pravidlo, které
není pole, se zahodí, a **skupiny existují jen do jedné úrovně** — skupina ve
skupině se zahodí. To omezení je záměrné: strom, který člověk dokáže přečíst, je
strom, který dokáže zkontrolovat. Nová skupina začíná s *opačným* combinatorem
než nejvyšší úroveň, protože skupina existuje, aby řekla „kterákoli“ uvnitř
„všechny“ (nebo naopak).

**Dotaz.** Celý strom se stane jedním uzávorkovaným `where (…)`, každé pravidlo
vnořeným `where` spojeným přes `and` / `or`. Pravidlo se přeskočí, dokud nic
neporovnává — nemá hodnotu a operátor ji potřebuje, nebo jeho pole není
constraint (zastaralý uložený pohled, podvržený payload). Skupina bez živého
pravidla se přeskočí s ním a builder bez živého pravidla není aktivní vůbec:
žádný chip, žádná cena dotazu. Sloupec každého pravidla se kvalifikuje vůči
základní tabulce, takže join přidaný jiným filtrem ho nemůže udělat
nejednoznačným. Builder nikdy neplánuje `QueryPlanner` — běží vždy přes vlastní
`apply()`.

**Kde to běží.** Přidání podmínky, přidání skupiny a odebrání jsou Livewire akce
na komponentě s `WithTable` — `queryBuilderAddRule()`, `queryBuilderAddGroup()`,
`queryBuilderRemove()` — a každá vrátí tabulku na první stránku. Výběr pole a
operátoru je vždy živý, protože rozhoduje, který prvek pro hodnotu se vykreslí.
Hodnoty se řídí panelem: živě, nebo se drží do Použít, když tabulka
[odkládá filtry](index.md#panel-filtru).

**Persistence.** Strom je pro query string příliš vnořený, takže se s
`Table::queryString()` do URL **nezapisuje**; uložený pohled ho zachová. Chip je
jedna věta složená z chipů jednotlivých constraintů — *Zákazník obsahuje Acme a
(Stav je jedním z Zaplaceno nebo …)* — a `->indicator()` ho nahradí.

**Pasti:**

- **Rozsah do podmínky nepatří.** `DateFilter` s `->range()` nebo `->presets()`
  a `NumberRangeFilter` mají dvě meze a žádnou jedinou hodnotu; podmínka
  vykreslí jeden prvek. Nechte je jako běžné filtry panelu vedle builderu.
- **Builder zabere celý řádek** mřížky panelu (`spansFullWidth()`), i při
  `Table::filtersFormColumns()`.
- **`->query()` na builderu nahradí všechno výše** a jako `$value` dostane
  normalizovaný strom; `->query()` na jednom constraintu běží jen pro tu
  podmínku, s její hodnotou.

## Základní použití

```php
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\QueryBuilderFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;

QueryBuilderFilter::make('conditions')
    ->label('Conditions')
    ->constraints([ // [tl! focus:start]
        TextFilter::make('customer'),
        SelectFilter::make('status')->options(['paid' => 'Paid', 'pending' => 'Pending']),
        DateFilter::make('issued_at')->label('Issued'),
    ]) // [tl! focus:end]
```

Panel ukáže *Přidat podmínku* a *Přidat skupinu*. Nová podmínka začíná na prvním
constraintu a na výchozím operátoru jeho filtru.

## Výběr operátorů

Každý constraint nabízí všechny operátory, které jeho typ podporuje, pokud je
nezúžíte. Zužte je přímo na constraintu, stejnými `->operators()` a
`->defaultOperator()`, jaké bere každý filtr:

```php
use NyonCode\WireTable\Enums\FilterOperator;

QueryBuilderFilter::make('conditions')->constraints([
    TextFilter::make('customer')
        ->operators(['contains', 'not_contains', 'is_empty']), // [tl! focus]
    TextFilter::make('number')
        ->label('Invoice')
        ->defaultOperator(FilterOperator::StartsWith), // stále všechny textové operátory [tl! focus]
    SelectFilter::make('status')
        ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
        ->operators([FilterOperator::In, FilterOperator::NotIn]),
])
```

| Constraint | Prvek pro hodnotu | Operátory, které může nabídnout |
|---|---|---|
| `TextFilter` | textový vstup | obsahuje, neobsahuje, je, není, začíná na, končí na, je prázdné, není prázdné |
| `SelectFilter` | možnosti filtru (vždy vícenásobně) | je jedním z, není žádným z, je prázdné, není prázdné |
| `DateFilter` (jedno datum) | vstup data | je dne, není dne, je před, je nejpozději, je po, je nejdříve, je prázdné, není prázdné |
| `TernaryFilter` | ano / ne | žádné — hodnota je porovnání |

Operátor, který nepotřebuje hodnotu (*je prázdné*), nevykreslí žádný prvek pro
hodnotu.

## Skupiny

*Přidat skupinu* připojí skupinu s jednou prázdnou podmínkou a s combinatorem
opačným k nejvyšší úrovni. Odebráním poslední podmínky skupiny se odebere i
skupina. Skupina ve skupině není.

## Pole přes relaci

Tečkový název constraintu je cesta relací, zpracovaná jako všude jinde: pozitivní
operátor je `whereHas`, negativní je `whereDoesntHave` s pozitivním porovnáním —
„žádný související záznam neodpovídá“, ne „nějaký související záznam neodpovídá“.

```php
QueryBuilderFilter::make('conditions')->constraints([
    TextFilter::make('customer.email')->label('Customer email'),
    SelectFilter::make('lines.product_id')
        ->label('Contains product')
        ->options(fn () => Product::pluck('name', 'id')->all()),
])
```

## Rozšířený příklad

Seznam faktur z náhledu ve workbenchi: tři rychlé filtry na každý den a query
builder pro otázku, kterou nikdo nepředvídal, vše v panelu nad řádky, který se
použije až na *Použít*.

```php
<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FiltersLayout;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\QueryBuilderFilter;
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
                TextColumn::make('number')->label('Invoice')->sortable(),
                TextColumn::make('customer')->label('Customer'),
                BadgeColumn::make('status')->label('Status'),
                TextColumn::make('issued_at')->label('Issued')->date(),
            ])
            ->filters([
                TextFilter::make('customer')->label('Customer')->operators(),
                SelectFilter::make('status')->label('Status')->multiple()
                    ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
                    ->withCounts(),
                DateFilter::make('issued_at')->label('Issued')->presets(),
                QueryBuilderFilter::make('conditions') // [tl! focus:start]
                    ->label('Conditions')
                    ->constraints([
                        TextFilter::make('customer')->label('Customer'),
                        TextFilter::make('number')->label('Invoice'),
                        SelectFilter::make('status')->label('Status')
                            ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue']),
                        DateFilter::make('issued_at')->label('Issued'),
                    ]), // [tl! focus:end]
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->deferFilters();
    }

    public function render()
    {
        return view('livewire.list-invoices');
    }
}
```

Builder se roztáhne přes celou šířku třísloupcové mřížky. Jeho constrainty jsou
nové instance, ne filtry panelu nad ním, takže se nikdy nedělí o stav.

## API QueryBuilderFilter

```php
QueryBuilderFilter::make(string $name)
->constraints(array $filters)          // list<Filter> — pole, o kterých podmínka může být; selecty se stanou vícenásobnými, operátory jsou ve výchozím stavu všechny podporované
->label(?string $label)                // zděděno — popisek nad podmínkami
->indicator(string|Closure|null $indicator) // zděděno — nahradí jednovětý chip; fn ($tree, Filter $filter)
->query(Closure $callback)             // zděděno — nahradí celý builder; fn (Builder $query, array $tree): Builder
->getConstraints(): array              // array<string, Filter> podle názvu
->getConstraint(?string $field): ?Filter
->getFirstConstraint(): ?Filter        // kde začíná nová podmínka
->hasActiveRule(mixed $state): bool    // zda by nějaká podmínka zúžila řádky
```

Akce komponenty, které volají tlačítka builderu (z `WithTable`):

```php
$component->queryBuilderAddRule(string $filter, ?int $group = null); // přidá podmínku, na nejvyšší úrovni nebo do skupiny $group
$component->queryBuilderAddGroup(string $filter);                    // přidá skupinu s jednou prázdnou podmínkou
$component->queryBuilderRemove(string $filter, int $index, ?int $group = null); // odebere podmínku; vyprázdněná skupina odejde s ní
```

## Související

- [Filtry](index.md) — operátory, panel filtrů a sdílené API filtrů
- [TextFilter](text.md), [SelectFilter](select.md), [DateFilter](date.md), [TernaryFilter](ternary.md) — constrainty
- [Filtry relací a podřádků](relationships.md) — tečkové názvy a sémantika relací
