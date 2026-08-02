---
order: 23
nav: false
---

# TagsColumn

Renders a multi-value state as a row of chips. The chip chrome is the same badge
surface as [BadgeColumn](badge.md), so a tag and a badge cannot drift apart.

```php
use NyonCode\WireTable\Columns\TagsColumn;
```

## Basic Usage

```php
TagsColumn::make('tags')               // ['php', 'laravel'] → two chips
```

Accepts an array, a JSON/array cast, or anything `Arrayable` — including a
relation collection loaded through a dot path:

```php
TagsColumn::make('skills.name')
```

## Delimited Strings

A plain string is one tag unless you say how to split it:

```php
TagsColumn::make('tags')
    ->separator()                      // default ','  → "php,laravel" = 2 chips
    ->separator('|')
```

Blank entries are dropped, so a trailing separator does not produce an empty chip.

## Limiting the Row

```php
TagsColumn::make('tags')
    ->limitList(3)                     // 3 chips, then a "+2" chip
```

## Colors

Per-value colors use the same `colors()` / `colorUsing()` vocabulary as
BadgeColumn, including enum self-coloring (see [Casts](casts.md)):

```php
TagsColumn::make('tags')
    ->colors(['urgent' => 'danger', 'later' => 'gray'])
```

## TagsColumn API

```php
->separator(?string $separator = ',')  // split a string state into tags
->limitList(?int $limit)               // show N chips, collapse the rest into "+N"
->colors(array|Closure $colors)        // per-value color map
->colorUsing(Closure $fn)
->getSeparator(): ?string
->getLimitList(): ?int
```
