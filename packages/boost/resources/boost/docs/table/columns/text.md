---
order: 23
summary: "The default cell: text with formatting presets, links, copying, descriptions and tooltips."
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

## Rich Content with Mentions

Stored editor content whose mentions are read back from the database on every
render, so a renamed record reads renamed in the table too:

```php
TextColumn::make('body')
    ->richContent()              // implies ->html()
    ->limit(120)
```

Implies [`html()`](index.md), because resolved mentions are markup — this is not
a second raw-HTML switch, it is what to do with the identities such markup holds.
Without it a mention shows the name it was written with, which is quietly wrong
rather than visibly broken.

> **It costs queries per row.** A cell is rendered on its own, so mentions batch
> within one cell and not across the page: twenty-five rows are twenty-five
> lookups. Worth it on a narrow table of documents; not worth it on a listing
> that only shows the first eighty characters, where `limit()` on plain text says
> the same thing for free.

See [TiptapEditor · Mentions](../../forms/fields/tiptap-editor.md#mentions).

## Complete TextColumn API

```php
->date(?string $format = null)       // date formatting
->dateTime(?string $format = null)   // datetime formatting
->since()                            // relative time (diffForHumans)
->money(string $currency)            // currency formatting
->numeric(int $decimals = 0, ?string $decimalSeparator = ',', ?string $thousandsSeparator = ' ')
->fontFamily(string $family)         // 'sans', 'serif', 'mono'
->richContent(bool $condition = true)  // resolve mentions; implies ->html()
->isRichContent(): bool
->isMoney(): bool
->getCurrency(): ?string
->isNumeric(): bool
```
