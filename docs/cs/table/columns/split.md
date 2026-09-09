---
order: 23
summary: Jedna buňka rozdělená vodorovně mezi několik podřízených sloupců, každý si drží vlastní vykreslení.
---

# SplitColumn

Vodorovně rozdělí prostor mezi několik dětských sloupců.

```php
use NyonCode\WireTable\Columns\SplitColumn;
```

## Základní split

```php
SplitColumn::make('name_status')
    ->columns([
        TextColumn::make('name')->weight('bold'),
        BadgeColumn::make('status')->colors([...]),
    ])
```

## Vertikální layout

```php
SplitColumn::make('address')
    ->columns([
        TextColumn::make('street'),
        TextColumn::make('city'),
        TextColumn::make('country'),
    ])
    ->vertical()
```

## S mezerou a zarovnáním

```php
SplitColumn::make('user_info')
    ->columns([
        ImageColumn::make('avatar')->circular()->size('sm'),
        TextColumn::make('name'),
    ])
    ->gap('sm')          // 'none' | 'xs' | 'sm' | 'md' | 'lg' | 'xl', nebo krok 0–12
    ->alignCenter()      // zarovnání na příčné ose, na střed
```

**Zarovnání jde po příčné ose**, tedy po té, která layoutu zbyde: ve výchozím
řádku je svislá, ve sloupci po `vertical()` vodorovná. Řádek se ve výchozím stavu
zarovnává na střed; sloupec roztahuje a roztahuje dál, dokud si neřeknete o něco
jiného — přidání `vertical()` tak nikdy nepřeskládá split, který už máte.

```php
SplitColumn::make('address')
    ->columns([TextColumn::make('street'), TextColumn::make('city')])
    ->vertical()
    ->alignStart()       // [tl! focus] k náběžné hraně místo dětí přes celou šířku
```

`gap()` bere obojí slovník — jméno z design systému, nebo krok na Tailwind škále
0–12 jako int či číselný řetězec — a obojí prochází sdíleným
`Foundation\Support\GapScale`, který z toho udělá **literální** utilitu. Co
nerozpozná, spadne na výchozí mezeru splitu místo toho, aby se na stránku dostala
třída, kterou Tailwind nikdy nevygeneroval.

## API SplitColumn

```php
->columns(array $columns)            // Column[] dětské sloupce
->vertical()                         // vertikální layout
->horizontal()                       // horizontální layout (výchozí)
->gap(string|int $gap)               // 'none'|'xs'|'sm'|'md'|'lg'|'xl', nebo krok 0–12
->getGapClass(): string              // literální utilita mezery
->alignCenter(bool $align = true)    // na příčné ose na střed
->alignStart()                       // na začátek příčné osy
->getAlignClass(): string            // '' dokud se o zarovnání neřeklo
->getColumns(): array
```

Co split odpovídá dotazovému švu za svoje děti:

```php
->isSearchable(): bool               // true, pokud je jakékoli dítě searchable
->getSearchColumns(): array          // sloučené z dětí
->isSortable(): bool                 // true, pokud je sortable kterékoli dítě, jinak vlastní příznak
->getSortColumn(): ?string           // první sortable dítě, jinak vlastní jméno
```

## Jak funguje řazení splitu

Split je zaregistrovaný pod jménem skupiny, kterou kreslí — výše `'identity'` — a
to jméno obvykle není atribut modelu. Dvě metody proto odpovídají na dvě různé
otázky:

- `isSortable()` udělá hlavičku klikací, jakmile je řaditelné **kterékoli** dítě.
  Padá zpět na vlastní příznak `->sortable()` splitu.
- `getSortColumn()` pojmenuje, podle čeho ten klik řadí: **první řaditelné dítě**,
  s pádem zpět na vlastní jméno splitu.

`TableQueryService` se ptá `getSortColumn()` místo toho, aby použil jméno, pod
kterým se na hlavičku kliklo — ty dva se tedy nikdy nemusí shodovat:

```php
SplitColumn::split([
    ImageColumn::make('avatar'),
    TextColumn::make('name')->sortable(),   // [tl! focus]
    TextColumn::make('email'),
], 'identity')
// hlavička hlásí „Identity", ORDER BY běží nad `name`
```

Když chcete, aby skupina řadila podle něčeho, co žádné z jejích dětí nezobrazuje,
zaregistrujte split pod skutečným atributem a označte ho `->sortable()` sami.
