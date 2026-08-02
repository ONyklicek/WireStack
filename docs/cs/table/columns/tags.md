---
order: 23
nav: false
---

# TagsColumn

Vykreslí vícehodnotový stav jako řadu chipsů. Vzhled chipsu je tentýž badge
povrch jako u [BadgeColumn](badge.md), takže se štítek a badge nemohou rozejít.

```php
use NyonCode\WireTable\Columns\TagsColumn;
```

## Základní použití

```php
TagsColumn::make('tags')               // ['php', 'laravel'] → dva chipsy
```

Přijímá pole, cast na pole/JSON i cokoli `Arrayable` — včetně kolekce relace
načtené přes tečkovou cestu:

```php
TagsColumn::make('skills.name')
```

## Řetězce s oddělovačem

Prostý řetězec je jeden štítek, dokud neřeknete, jak ho rozdělit:

```php
TagsColumn::make('tags')
    ->separator()                      // výchozí ','  → "php,laravel" = 2 chipsy
    ->separator('|')
```

Prázdné položky se zahazují, takže koncový oddělovač nevytvoří prázdný chips.

## Omezení počtu

```php
TagsColumn::make('tags')
    ->limitList(3)                     // 3 chipsy, pak chips „+2"
```

## Barvy

Barvy podle hodnoty používají stejný slovník `colors()` / `colorUsing()` jako
BadgeColumn, včetně samobarvicích enumů (viz [Casty](casts.md)):

```php
TagsColumn::make('tags')
    ->colors(['urgent' => 'danger', 'later' => 'gray'])
```

## TagsColumn API

```php
->separator(?string $separator = ',')  // rozdělí řetězcový stav na štítky
->limitList(?int $limit)               // zobrazí N chipsů, zbytek sloučí do „+N"
->colors(array|Closure $colors)        // mapa barev podle hodnoty
->colorUsing(Closure $fn)
->getSeparator(): ?string
->getLimitList(): ?int
```
