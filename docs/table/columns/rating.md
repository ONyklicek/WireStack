---
order: 23
nav: false
---

# RatingColumn

Renders a numeric state as a row of filled and empty stars — the read-only table
counterpart of the `Rating` form field, sharing its vocabulary.

```php
use NyonCode\WireTable\Columns\RatingColumn;
```

## Basic Usage

```php
RatingColumn::make('score')            // 3 → ★★★☆☆
```

A non-numeric or null state renders the column's empty text instead of an empty
star row.

## Scale, Halves and the Value

```php
RatingColumn::make('score')
    ->max(10)                          // default: 5
    ->allowHalf()                      // 2.5 draws a half-filled star
    ->showValue()                      // print the number beside the stars
```

Without `allowHalf()` a fractional value simply fills the stars it has passed.

## Colors and Icons

```php
RatingColumn::make('score')
    ->color('warning')                 // filled-star color, default: 'warning'
    ->icons('star', 'outline:star')    // filled, empty
```

## Accessibility

The star row is a single `role="img"` labelled "3 out of 5" (translated), so a
screen reader announces the value once rather than reading five icons.

## RatingColumn API

```php
->max(int $max)                              // default: 5
->allowHalf(bool $condition = true)
->color(string|Color|null $color)            // default: 'warning'
->icons(string|Icon $filled, string|Icon $empty)
->showValue(bool $condition = true)
->getMax(): int
->isAllowHalf(): bool
```
