---
order: 31
summary: Volný text porovnaný jedním způsobem, který zvolíte, nebo výběr porovnání pro uživatele — obsahuje, je, začíná na, jejich negace a „je prázdné“.
---

# TextFilter

Textový filtr. Ve výchozím stavu substringové `LIKE`, takže funguje jako
vyhledávací pole omezené na jeden sloupec; jedno volání nastaví jiné porovnání
(prefix, přesná shoda, …) a `->operators()` předá volbu uživateli s výběrem
vedle vstupu. Je to zároveň engine za textovým filtrem hlavičky sloupce
(`->filterable()`).

```php
use NyonCode\WireTable\Filters\TextFilter;
```

## Jak to funguje

**Jedno porovnání, nebo volba.** `->operator()` pevně nastaví porovnání, které
filtr dělá; uživatel vidí jen textové pole. `->operators()` vedle něj vykreslí
výběr s operátory, které vyjmenujete (nebo se všemi), a stav se stane
`{value, operator}`. Platné porovnání se určí v tomto pořadí:

1. operátor, který uvádí stav — jen pokud ho filtr nabízí (zastaralý uložený
   pohled nebo podvržená URL s jiným se ignoruje);
2. `->defaultOperator()`;
3. `->operator()` — `like` (obsahuje), pokud nenastavíte jiný;
4. pokud ten mezi nabízenými není, první nabízený.

**Co které porovnání stojí.** *obsahuje*, *začíná na*, *končí na*, *je* a
porovnání jsou jedna SQL klauzule a jdou přes kanonický `QueryPlanner`, takže
joiny a kvalifikace sloupců se řeší jako u každého jiného filtru. Tři nemohou být
jednou klauzulí a omezí dotaz samy:

| Operátor | SQL |
|---|---|
| neobsahuje | `column IS NULL OR column NOT LIKE '%v%'` |
| není | `column IS NULL OR column != 'v'` |
| je prázdné | `column IS NULL OR column = ''` |
| není prázdné | `column IS NOT NULL AND column != ''` |

Negace **ponechají řádky s `NULL`** — „neobsahuje gmail“ zahrne i zákazníky bez
e-mailu — a u textu *je prázdné* najde i `''`. Přes relaci (tečkový název) je
negace `whereDoesntHave` s pozitivním porovnáním: zákazník s jednou adresou na
gmailu a jednou jinou je vyloučen.

**Co uživatel napíše, se hledá doslova.** `%` a `_` se před vložením do vzoru
escapují, takže *obsahuje* `50%` najde „50% sleva“, ne každý řádek s číslem 50.
Shoda ignoruje velikost písmen na každém enginu — na PostgreSQL je to `ILIKE`,
stejně jako u vyhledávání.

**Výchozí stav.** Bez seznamu operátorů není výběr: filtr porovnává tak, jak říká
`->operator()`, a jeho stav je prostá hodnota. Operátor, který nepotřebuje
hodnotu (*je prázdné*, *není prázdné*), udělá filtr aktivním i s prázdným
textovým polem.

**Past.** `->operator('>=')` a ostatní zápisy porovnání zůstávají pro pevné
porovnání, ale ve slovníku výběru nejsou. V kombinaci s `->operators()` výchozí
hodnota, kterou výběr nenabízí, spadne na první nabízený operátor — použijte
místo toho `->defaultOperator()` s jedním z vyjmenovaných.

## Základní použití

```php
TextFilter::make('name')
// WHERE name LIKE '%value%'
```

## Pevné porovnání

```php
TextFilter::make('sku')
    ->operator('starts_with')   // WHERE sku LIKE 'value%'

TextFilter::make('email')
    ->operator('=')             // přesná shoda

TextFilter::make('age')
    ->operator('>=')            // číselné / lexikální porovnání
```

Přijímá: `like` (výchozí), `starts_with`, `ends_with`, `equals` / `=`, `!=` /
`<>`, `>`, `>=`, `<`, `<=` a jakoukoli hodnotu `FilterOperator` (`not_contains`,
`is_empty`, …).

## Volba pro uživatele

```php
use NyonCode\WireTable\Enums\FilterOperator;

TextFilter::make('email')
    ->operators()                                 // všechny textové operátory

TextFilter::make('email')
    ->operators(['contains', 'not_contains', 'ends_with', 'is_empty'])
    ->defaultOperator(FilterOperator::EndsWith)   // na čem nový filtr začne
```

Textové operátory: *obsahuje*, *neobsahuje*, *je*, *není*, *začíná na*, *končí
na*, *je prázdné*, *není prázdné*. Operátor, který textový filtr neumí použít, se
ze seznamu vypustí. Se zapnutým výběrem chip říká porovnání
(`Email: neobsahuje gmail`, `Telefon: je prázdné`) a `Table::queryString()`
zapíše operátor pod parametr filtru s příponou `_op`.

## Vlastní query logika

```php
TextFilter::make('title')
    ->query(fn (Builder $query, $value) => $query->where('title', 'like', "%{$value}%"))
```

Callback `->query()` nahradí porovnání, s výběrem i bez něj.

## Rozšířený příklad

Seznam zákazníků, kde podpora hledá „všechny zákazníky bez telefonu“ nebo
„všechny, kdo nejsou na gmailu“ — otázky, které pole jen s „obsahuje“ položit
neumí.

```php
<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

class ListCustomers extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Customer::class)
            ->columns([
                TextColumn::make('name')->sortable(),
                TextColumn::make('email'),
                TextColumn::make('phone'),
            ])
            ->filters([
                TextFilter::make('name')->debounce(300), // obsahuje, bez výběru
                TextFilter::make('email') // [tl! focus:start]
                    ->operators(['contains', 'not_contains', 'ends_with', 'is_empty'])
                    ->defaultOperator(FilterOperator::EndsWith),
                TextFilter::make('phone')
                    ->operators([FilterOperator::IsEmpty, FilterOperator::IsNotEmpty]), // [tl! focus:end]
            ])
            ->queryString();
    }

    public function render()
    {
        return view('livewire.list-customers');
    }
}
```

`?filter_email=gmail.com&filter_email_op=not_contains` zopakuje „není na gmailu“,
včetně e-mailů s `NULL`.

## TextFilter API

```php
->operator(string $operator)         // pevné porovnání — výchozí 'like'; také '=', '!=', '<>', '>', '>=', '<', '<=' nebo hodnota FilterOperator
->operators(array|bool $operators = true) // zděděno — nabídne výběr: true = všechny textové operátory, nebo seznam FilterOperator|string
->defaultOperator(FilterOperator|string $operator) // zděděno — na čem výběr začne
->debounce(?int $ms)                 // debounce živého vstupu
->query(Closure $fn)                 // vlastní query: fn (Builder $query, $value): Builder
->getOperator(): string              // pevné porovnání tak, jak je nastavené
```

## Související

- [Filtry](index.md#operatory) — slovník operátorů, který sdílí každý filtr
- [Filtry na úrovni sloupce](column-level.md) — tentýž filtr v hlavičce sloupce, s `->filterOperators()`
- [QueryBuilderFilter](query-builder.md) — textové podmínky spojené přes AND / OR
