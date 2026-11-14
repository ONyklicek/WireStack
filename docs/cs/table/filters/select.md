---
order: 31
summary: Rozbalovací seznam nad předdefinovanými možnostmi nebo vlastními hodnotami sloupce, s počty záznamů a „není žádným z“ na požádání — filtr, po kterém většina tabulek sáhne první.
---

# SelectFilter

Rozbalovací filtr pro předdefinované možnosti. Nejběžnější typ filtru.

Vykresluje se přes stejný combobox jako `Select` field ve formulářích, takže
otevřený filtr vypadá stejně jako otevřený formulářový select. Nativní `<select>`
prohlížeče je opt-in přes [`->native()`](#nativni-html-select).

```php
use NyonCode\WireTable\Filters\SelectFilter;
```

## Jak to funguje

**Odkud se berou možnosti**, v tomto pořadí:

1. `->options()` — pole, closure (vyhodnocená při renderu) nebo třída enumu.
2. Když žádné nejsou a je zapnuté `->optionsFromColumn()`, vlastní odlišné
   hodnoty sloupce, načtené z dat — nejčastější první, omezené na `$limit`, bez
   `NULL`, každá s popiskem sama sebe (nebo popiskem svého enumu).
3. U sloupce přetypovaného na enum case toho enumu.

**Jak porovnává.** Bez výběru operátoru je jedna hodnota `=` a hodnota s
`->multiple()` je `whereIn`, přes `whereHas` u tečkového (relačního) názvu. S
`->operators()` filtr mluví o příslušnosti: *je jedním z* (`IN`, jedna klauzule
planneru), *není žádným z* (`IS NULL OR NOT IN` — řádky bez hodnoty zůstanou),
*je prázdné* a *není prázdné* (`IS NULL` / `IS NOT NULL`). Přes relaci je *není
žádným z* `whereDoesntHave` s `IN`: příspěvek se štítky `php` i `go` není „žádný
z php“. Jakmile je výběr zapnutý, hodnota se bere jako seznam, i bez
`->multiple()`.

**Počty.** `->withCounts()` vypíše u každé možnosti, kolik záznamů by po jejím
výběru zůstalo — `Admin (3)`. Počet se bere pod **všemi ostatními filtry a
vyhledáváním, ale ne pod vlastní hodnotou tohoto filtru**: počítat *Roli* pod
„Role je Admin“ by tvrdilo, že každá jiná role nemá nic, a odradilo by uživatele
od změny názoru. Každý počítající filtr je jeden seskupený dotaz (`GROUP BY
column`, seřazený podle počtu), sestavený stejnou query službou jako řádky, takže
se počet nikdy nerozchází s tím, co výběr ukáže. Možnost, kterou data nemají,
ukáže `(0)`. Chip si nechá prostý popisek — `Role: Admin`, ne
`Role: Admin (3)`.

**Drží se mezi requesty.** Seskupený dotaz je to nejdražší, co render dělá, a
zaškrtnutí řádku ani otevření modalu jeho odpověď nezmění, takže se počty
cachují — podle samotného dotazu (jiný filtr nebo hledaný výraz je jiný záznam)
a podle generace zápisů tabulky, takže inline úprava nebo akce přes tabulku je
okamžitě zneplatní. Co tabulka nevidí — zápis provedený jinde — se ukáže do
`wire-table.defaults.facet_cache_ttl` sekund (výchozí 60; `0` počítá při každém
requestu). Tabulka s [`cacheQuery()`](../advanced.md) je drží stejně dlouho jako
své řádky. Cache patří **komponentě jednoho uživatele na jedné databázi** a nikdy
se nesdílí: stejné SQL neznamená stejná data, když mají tenanti vlastní databázi
nebo schéma, a seznam možností načtený z dat by jinak jednomu tenantovi ukázal
hodnoty jiného.

**Nepočítá se** — možnosti fungují dál, jen bez čísel a bez seznamu načteného z
dat:

- tečkový (relační) název — není jediný sloupec, podle kterého seskupit;
- tabulka nad vlastním [datovým zdrojem](../data-sources.md) — počítá se jen Eloquent;
- hlavičkový filtr sloupce, který u některých záznamů skrývá buňku přes
  `visibleForRecord()` — seznam hodnot by vypsal hodnoty, které ty buňky skrývají.

## Základní použití

```php
SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'banned' => 'Banned',
    ])
```

## S placeholderem

První položka je vždy prázdná možnost „All“. Pro přizpůsobení:

```php
SelectFilter::make('role')
    ->options([
        '' => 'All Roles',           // explicitní placeholder
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ])
```

## Vícenásobný výběr

```php
SelectFilter::make('tags')
    ->options(Tag::pluck('name', 'id')->toArray())
    ->multiple()
    ->label('Tags')
```

Při `multiple()` aplikuje `whereIn()` místo `where()`.

## Vyhledávatelný rozbalovací seznam

```php
SelectFilter::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->label('Country')
```

Přidá do rozbalovacího seznamu vyhledávací input. Povrch je stejný combobox jako u
nevyhledávatelného filtru — jen navíc s vyhledávacím inputem.

## Nativní HTML select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // nativní <select> element prohlížeče (rychlejší render)
```

Odhlásí filtr ze sdíleného comboboxu, takže přestane odpovídat formulářovému
selectu. Používejte jen tam, kde je cena renderu důležitější než jednotný vzhled.

`->nativeOnMobile()` ponechá combobox od mobilního breakpointu filtru výš a pod
ním vykreslí `<select>` prohlížeče, kde telefon místo sheetu otevře vlastní výběr:

```php
SelectFilter::make('type')
    ->options([...])
    ->nativeOnMobile()               // telefon: nativní <select>; desktop: combobox
```

Volba platí v panelu filtrů i v řádku filtrů v hlavičce sloupců — oba vykreslují
stejný prvek, včetně filtru s `multiple()`. `->touchOnMobile()` místo toho
otevře [dotykový seznam](../../forms/fields/select.md#dotykovy-seznam-na-telefonu),
na obou místech. `->native()` nad ní vyhrává; výchozí hodnota pro celou aplikaci je
`wire-core.mobile.native` (viz [mobil](../../start/configuration.md#mobil)).

## Z databáze

```php
SelectFilter::make('department')
    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')->toArray())
```

Možnosti mohou být closure — vyhodnocují se líně při renderu.

## Z enumu

Předejte třídu PHP enumu místo pole — jeho case se rozvinou na možnosti `value => label`.
Popisky pocházejí z `getLabel()`, když enum implementuje `Foundation\Contracts\Enum\HasLabel`,
jinak se z názvu case udělá headline.

```php
SelectFilter::make('status')->options(OrderStatus::class)
```

> Když je atribut modelu **přetypován** na enum, `SelectFilter` na tom sloupci
> automaticky naplní své možnosti z enumu i bez volání `->options()`. Tato zkratka je
> pro případy, kdy filtr nastavujete explicitně.

## Počty záznamů

```php
SelectFilter::make('status')
    ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
    ->multiple()
    ->withCounts()                   // Paid (12), Pending (3), Overdue (0)
```

## Možnosti z dat

Když jsou hodnoty otevřené — země, štítek, dodavatel — nechte sloupec dodat vlastní
seznam:

```php
SelectFilter::make('country')
    ->optionsFromColumn(limit: 50)   // 50 nejčastějších hodnot, načtených z řádků
    ->withCounts()
    ->searchable()
    ->multiple()
```

Vypíšou se jen hodnoty, které se vyskytují pod ostatními filtry, takže se seznam
zužuje, jak uživatel zužuje tabulku. Hodnotu za limitem vybrat nelze; zvyšte
limit, nebo pro uzavřenou množinu dejte `->options()`.

## Není žádným z

```php
use NyonCode\WireTable\Enums\FilterOperator;

SelectFilter::make('role')
    ->options(['admin' => 'Admin', 'editor' => 'Editor', 'viewer' => 'Viewer'])
    ->multiple()
    ->operators()                                    // je jedním z, není žádným z, je prázdné, není prázdné
    ->defaultOperator(FilterOperator::NotIn)
```

Select může nabídnout operátory *je jedním z*, *není žádným z*, *je prázdné* a
*není prázdné*; jakýkoli jiný v seznamu se vypustí. Chip říká porovnání —
`Role: není žádným z Admin, Editor`.

## Vlastní dotaz

```php
SelectFilter::make('has_avatar')
    ->options([
        'yes' => 'With Avatar',
        'no' => 'Without Avatar',
    ])
    ->query(fn (Builder $query, string $value) => match($value) {
        'yes' => $query->whereNotNull('avatar_url'),
        'no' => $query->whereNull('avatar_url'),
    })
```

## Rozšířený příklad

Seznam objednávek, kde filtr stavu ukazuje, co by která volba ponechala, a země
zákazníka se vybírá ze zemí, které se opravdu vyskytují.

```php
<?php

namespace App\Livewire;

use App\Enums\OrderStatus;
use App\Models\Order;
use Livewire\Component;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

class ListOrders extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Order::class)
            ->columns([
                TextColumn::make('number')->sortable(),
                BadgeColumn::make('status'),
                TextColumn::make('country'),
            ])
            ->filters([
                SelectFilter::make('status') // [tl! focus:start]
                    ->options(OrderStatus::class)
                    ->multiple()
                    ->operators()
                    ->withCounts(),
                SelectFilter::make('country')
                    ->optionsFromColumn(limit: 50)
                    ->withCounts()
                    ->searchable()
                    ->multiple(), // [tl! focus:end]
            ]);
    }

    public function render()
    {
        return view('livewire.list-orders');
    }
}
```

Výběr *Země je jedním z CZ* přepočítá pod ním *Stav*; samotné počty u *Země*
vybranou zemi ignorují, takže ostatní země si svá čísla ponechají.

## API SelectFilter

```php
->options(array|string|Closure $options) // ['value' => 'Label', ...] nebo třída enumu
->multiple(bool $multiple = true)    // režim multi-select
->searchable(bool $searchable = true) // přidá vyhledávací input do rozbalovacího seznamu
->native(bool $native = true)        // opt-in nativní <select> prohlížeče (výchozí: false)
->nativeOnMobile(bool $condition = true) // nativní <select> jen pod mobilním breakpointem (výchozí: config wire-core.mobile.native)
->touchOnMobile(bool $condition = true)  // dotykový seznam přes celou výšku pod mobilním breakpointem (výchozí: config wire-core.mobile.touch)
->withCounts(bool $condition = true) // vypíše počet záznamů u každé možnosti, pod ostatními filtry — výchozí false
->optionsFromColumn(bool $condition = true, int $limit = 100) // možnosti = odlišné hodnoty sloupce, nejčastější první, omezené na $limit
->operators(array|bool $operators = true) // zděděno — 'in'|'not_in'|'is_empty'|'is_not_empty'; true = všechny čtyři
->defaultOperator(FilterOperator|string $operator) // zděděno — výchozí 'in'
->getOptions(): array                // hodnota => popisek, bez počtů
->getDisplayOptions(): array         // hodnota => popisek tak, jak se vykreslí, s " (n)" při počítání
```

## Související

- [Filtry](index.md#operatory) — slovník operátorů, který sdílí každý filtr
- [Filtry na úrovni sloupce](column-level.md#pocty-a-seznamy-hodnot) — `->filterWithCounts()` a `->filterAsValueList()` na sloupci
- [QueryBuilderFilter](query-builder.md) — select jako jedna podmínka z několika
