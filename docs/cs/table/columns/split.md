---
order: 23
summary: Jedna buňka rozdělená vodorovně mezi několik podřízených sloupců, každý si drží vlastní vykreslení.
---

# SplitColumn

Vodorovně rozdělí prostor mezi několik dětských sloupců.

```php
use NyonCode\WireTable\Columns\SplitColumn;
```

## Jak to funguje

Split vykreslí každé dítě jeho vlastním `renderCell()` — s jeho formátováním,
prefixem, stylem textu, ikonou i odkazem — a výsledky rozloží do jednoho flex
kontejneru: ve výchozím stavu do řádku, po `vertical()` do sloupce. Dítě typu
`ImageColumn` z toho toku vypadne a kreslí se jako první, vedle ostatních.

- **Každé dítě má vlastní box.** Výstup dítěte bývá holý text a holý text vložený
  do flex kontejneru je jedna anonymní položka — dvě částky pod sebou se dřív
  vypsaly jako `500 Kč1 000 Kč` na jednom řádku. Dítě, které nic nevykreslí
  (skryté, nebo neviditelné pro daný záznam), box nedostane, takže nenechá mezeru.
- **Zarovnání má dva zdroje.** Dítě nikdy nedostane vlastní `<td>`, takže jeho
  `alignRight()` jde na jeho box; dítě bez zarovnání dědí to splitu. Vlastní
  `alignRight()` splitu zarovná text splitu po `vertical()` a v řádku posune děti
  na konec buňky (`justify-end`). `alignCenter()` a `alignStart()` jsou něco
  jiného: na splitu zarovnávají děti na **příčné ose**, jak popisuje
  [S mezerou a zarovnáním](#s-mezerou-a-zarovnanim).
- **Hledání a řazení dosáhne na děti.** Split je prohledávatelný, jakmile je
  prohledávatelné kterékoli dítě, a dotaz hledá v každém takovém dítěti — v jeho
  vlastních sloupcích `searchable(['first_name', 'last_name'])`, pokud je
  vyjmenuje, jinak podle jeho jména. Samotný split `->searchable()` označovat
  nemusíte. Řadí se podle prvního řaditelného dítěte; viz
  [Jak funguje řazení splitu](#jak-funguje-razeni-splitu).
- **Dítě není sloupec tabulky.** Nemá vlastní hlavičku, přepínač ani souhrn a jeho
  `->sortable()` se počítá jen tehdy, když je prvním řaditelným dítětem.

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

## Dvě hodnoty pod sebou

Zaplacená částka nad celkem, ke kterému patří: `vertical()` skládá děti pod sebe,
`prefix()` dopíše spojku a styl textu druhého dítěte ho ztlumí. Obě zůstávají
prohledávatelné a hlavička řadí podle zaplacené částky.

```php
SplitColumn::make('pay_status')
    ->label('Zaplaceno')
    ->vertical()
    ->gap('none')
    ->columns([
        TextColumn::make('paid_amount')->money('Kč')->weight('medium')->sortable()->searchable(),
        TextColumn::make('total_amount')->money('Kč')->prefix('z ')->textColor('muted')->textSize('sm')->searchable(), // [tl! focus]
    ])
```

```text
500 Kč
z 1 000 Kč
```

Přidejte splitu `->alignRight()` a částky se srovnají k pravému okraji buňky, jak
peněžní sloupec obvykle sedí.

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
->getJustifyClass(): string          // 'justify-*' řádku z alignRight()/alignLeft(), '' když není nastaveno nebo je vertical
->getColumns(): array
```

Co split odpovídá dotazovému švu za svoje děti:

```php
->hasCapability(Capability $capability): bool  // Searchable/Sortable: true, má-li ji kterékoli dítě, jinak vlastní příznak
->isSearchable(): bool               // true, pokud je jakékoli dítě searchable
->getSearchColumns(): array          // explicitní sloupce hledání každého searchable dítěte, jinak jeho jméno
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
