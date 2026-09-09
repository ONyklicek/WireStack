---
order: 23
summary: "Výchozí buňka: text s formátovacími presety, odkazy, kopírováním, popisky a tooltipy."
---

# TextColumn

Univerzální textový sloupec s presety formátování.

```php
use NyonCode\WireTable\Columns\TextColumn;
```

## Základní použití

```php
TextColumn::make('name')
    ->sortable()
    ->searchable()

TextColumn::make('email')
    ->searchable()
    ->copyable()
    ->copyMessage('Copied!')
    ->icon('mail')
```

## Formátování data/času

```php
// PHP date formát
TextColumn::make('created_at')
    ->dateTime('d.m.Y H:i')
    ->sortable()

// Jen datum
TextColumn::make('birth_date')
    ->date('j. F Y')

// Relativní čas
TextColumn::make('last_login')
    ->since()                    // "2 hours ago", "3 days ago"
    ->sortable()
    ->tooltip(fn ($r) => $r->last_login?->format('d.m.Y H:i:s'))
```

## Formátování měny

```php
TextColumn::make('price')
    ->money('CZK')              // "1 234,50 CZK"
    ->sortable()
    ->alignRight()

TextColumn::make('salary')
    ->money('USD')              // "$1,234.50"
    ->summarize('sum', 'Total')
```

## Numerické formátování

```php
TextColumn::make('quantity')
    ->numeric(
        decimals: 0,
        thousandsSeparator: ' '
    )
    ->alignRight()
    ->sortable()

TextColumn::make('percentage')
    ->numeric(decimals: 1)
    ->suffix('%')
```

## Font family

```php
TextColumn::make('code')
    ->fontFamily('mono')         // monospace písmo

TextColumn::make('quote')
    ->fontFamily('serif')
```

## Bohatý obsah se zmínkami

Uložený obsah editoru, jehož zmínky se při každém renderu načtou znovu z
databáze — přejmenovaný záznam je tak přejmenovaný i v tabulce:

```php
TextColumn::make('body')
    ->richContent()              // implikuje ->html()
    ->limit(120)
```

Implikuje [`html()`](index.md), protože rozbalené zmínky jsou markup — není to
druhý přepínač na raw HTML, ale rozhodnutí, co s identitami, které takový markup
nese. Bez toho zmínka ukáže jméno, se kterým byla napsána, což je tiše špatně,
ne viditelně rozbité.

> **Stojí to dotazy na řádek.** Buňka se renderuje sama za sebe, takže se zmínky
> dávkují v rámci jedné buňky, ne napříč stránkou: dvacet pět řádků je dvacet pět
> vyhledání. Vyplatí se to na úzké tabulce dokumentů; ne na výpisu, který ukazuje
> jen prvních osmdesát znaků, kde `limit()` nad prostým textem řekne totéž zdarma.

Viz [TiptapEditor · Zmínky](../../forms/fields/tiptap-editor.md#zminky).

## Kompletní API TextColumn

```php
->date(?string $format = null)       // formátování data
->dateTime(?string $format = null)   // formátování datetime
->since()                            // relativní čas (diffForHumans)
->money(string $currency)            // formátování měny
->numeric(int $decimals = 0, ?string $decimalSeparator = ',', ?string $thousandsSeparator = ' ')
->fontFamily(string $family)         // 'sans', 'serif', 'mono'
->richContent(bool $condition = true)  // rozbalí zmínky; implikuje ->html()
->isRichContent(): bool
->isMoney(): bool
->getCurrency(): ?string
->isNumeric(): bool
```
