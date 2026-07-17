---
order: 23
nav: false
---

# TextColumn

General-purpose text column with formatting presets.

```php
use NyonCode\WireTable\Columns\TextColumn;
```

## Basic Usage

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

## Date/Time Formatting

```php
// PHP date format
TextColumn::make('created_at')
    ->dateTime('d.m.Y H:i')
    ->sortable()

// Date only
TextColumn::make('birth_date')
    ->date('j. F Y')

// Relative time
TextColumn::make('last_login')
    ->since()                    // "2 hours ago", "3 days ago"
    ->sortable()
    ->tooltip(fn ($r) => $r->last_login?->format('d.m.Y H:i:s'))
```

## Money Formatting

```php
TextColumn::make('price')
    ->money('CZK')              // "1 234,50 CZK"
    ->sortable()
    ->alignRight()

TextColumn::make('salary')
    ->money('USD')              // "$1,234.50"
    ->summarize('sum', 'Total')
```

## Numeric Formatting

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

## Font Family

```php
TextColumn::make('code')
    ->fontFamily('mono')         // monospace font

TextColumn::make('quote')
    ->fontFamily('serif')
```

## Complete TextColumn API

```php
->date(?string $format = null)       // date formatting
->dateTime(?string $format = null)   // datetime formatting
->since()                            // relative time (diffForHumans)
->money(string $currency)            // currency formatting
->numeric(int $decimals = 0, ?string $decimalSeparator = ',', ?string $thousandsSeparator = ' ')
->fontFamily(string $family)         // 'sans', 'serif', 'mono'
->isMoney(): bool
->getCurrency(): ?string
->isNumeric(): bool
```
