---
order: 23
nav: false
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

## Kompletní API TextColumn

```php
->date(?string $format = null)       // formátování data
->dateTime(?string $format = null)   // formátování datetime
->since()                            // relativní čas (diffForHumans)
->money(string $currency)            // formátování měny
->numeric(int $decimals = 0, ?string $decimalSeparator = ',', ?string $thousandsSeparator = ' ')
->fontFamily(string $family)         // 'sans', 'serif', 'mono'
->isMoney(): bool
->getCurrency(): ?string
->isNumeric(): bool
```
