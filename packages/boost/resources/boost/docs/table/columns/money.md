---
order: 23
nav: false
---

# MoneyColumn

An amount, presented the way a figure is read: against a right edge, in digits
of equal width, on one line.

```php
use NyonCode\WireTable\Columns\MoneyColumn;
```

## How It Works

The formatting is not this column's. `money()` lives on the shared
`FormatsState` concern, so `TextColumn::make('total')->money()` produces exactly
the same string — and an infolist `TextEntry` formats the same value the same
way. What a dedicated type changes is the column's **defaults**, and for money
three of them are wrong out of the box.

**It aligns right.** Amounts are compared down a column, and that only works
against a fixed right edge. In this framework the alignment carries a second
meaning: the stacked mobile card derives its *metric* — the one figure a phone
shows on the card headline — from the last right-aligned column. A money column
becomes that figure without being told, so getting the desktop right settles the
phone too.

**Its digits are tabular.** Proportional digits give `1` and `7` different
widths, so decimal points wander even under a right edge. `tabular-nums` is the
same vocabulary the summary footers and the mobile card already use.

**It does not wrap.** An amount broken across two lines stops being one number.

All three are defaults, not rules — see [Overriding the defaults](#overriding-the-defaults).

## Basic Usage

```php
MoneyColumn::make('total')                    // 1 234,50 CZK
MoneyColumn::make('total')->currency('EUR')   // 1 234,50 EUR
MoneyColumn::make('total')->withoutCurrency() // 1 234,50
```

## Precision and separators

The defaults are the Czech convention — two decimals, a comma, a thin space —
with one inherited special case worth knowing:

```php
MoneyColumn::make('total')->currency('CZK')   // 1 234,50 CZK   ← hellers
MoneyColumn::make('total')->currency('Kč')    // 1 235 Kč       ← whole crowns
```

The precision is keyed on **how the currency is spelled**, not on what it is.
That is not a rule worth inventing, and it is kept only because tables already
depend on it. State what you want instead of relying on it:

```php
MoneyColumn::make('total')
    ->money('EUR', decimals: 2, decimalSeparator: '.', thousandsSeparator: ',')
// 1,234.50 EUR
```

An omitted argument never overwrites a setting made earlier, so the sugar is
safe to chain:

```php
MoneyColumn::make('total')
    ->money('EUR', 2, '.', ',')
    ->currency('USD')      // keeps the separators, changes only the currency
// 1,234.50 USD
```

## Currency placement

Placement is a property of the currency's convention, not of the number, so it
is stated rather than guessed — there is no locale table behind this and
inferring one from a three-letter code would be wrong more often than not:

```php
MoneyColumn::make('total')
    ->money('$', 2, '.', ',')
    ->currencyBefore()
// $ 1,234.50
```

## In a table

A money column is an ordinary column: it sorts, it summarises, and it carries
the figure to the phone.

```php
public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('number')->sortable(),
            TextColumn::make('customer.name')->label('Customer'),
            BadgeColumn::make('state'),
            MoneyColumn::make('total')                    // [tl! focus]
                ->currency('EUR')                         // [tl! focus]
                ->sortable()                              // [tl! focus]
                ->summarizeSum('Total'),                  // [tl! focus]
        ])
        ->stackedOnMobile();   // the amount is the card's metric — see below
}
```

Below the stacking breakpoint each row becomes a card, and `total` lands on the
headline next to the invoice number rather than in the label/value grid at the
bottom — because it is the last right-aligned column. Nothing declares that
twice; see [Stacked on Mobile](../advanced.md#stacked-on-mobile).

## Overriding the defaults

Every default is a default:

```php
MoneyColumn::make('total')
    ->alignment('left')     // …and it stops being the card's metric
    ->textSize('lg')        // size, weight, colour and font family are untouched
```

Dropping the right alignment also drops the mobile metric derivation, which is
the point: the derivation follows the alignment, not the class.

## Using the formatter without the column

Any `TextColumn` — and any infolist `TextEntry` — takes the same call:

```php
TextColumn::make('total')->money('EUR', 2, '.', ',')->alignRight()
```

Prefer `MoneyColumn` when the value is money; prefer `->money()` when a column
is mostly something else and happens to be numeric.

## MoneyColumn API

```php
->currency(?string $currency)                  // the currency to render in
->withoutCurrency()                            // a bare formatted amount
```

Inherited from `TextColumn` / `FormatsState`:

```php
->money(?string $currency = 'CZK', ?int $decimals = null, ?string $decimalSeparator = null, ?string $thousandsSeparator = null)
->currencyBefore(bool $before = true)
->usesCurrencyBefore(): bool
->getMoneyDecimals(): int
->isMoney(): bool
->getCurrency(): ?string
```
