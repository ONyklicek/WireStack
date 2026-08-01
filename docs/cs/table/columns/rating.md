---
order: 23
nav: false
---

# RatingColumn

Vykreslí číselný stav jako řadu plných a prázdných hvězdiček — read-only tabulkový
protějšek formulářového pole `Rating`, se stejným slovníkem.

```php
use NyonCode\WireTable\Columns\RatingColumn;
```

## Základní použití

```php
RatingColumn::make('score')            // 3 → ★★★☆☆
```

Nečíselný nebo prázdný stav vykreslí prázdný text sloupce, ne řadu prázdných
hvězdiček.

## Škála, poloviny a hodnota

```php
RatingColumn::make('score')
    ->max(10)                          // výchozí: 5
    ->allowHalf()                      // 2,5 vykreslí poloviční hvězdičku
    ->showValue()                      // vypíše číslo vedle hvězdiček
```

Bez `allowHalf()` se desetinná hodnota jen zaokrouhlí dolů na počet plných
hvězdiček.

## Barvy a ikony

```php
RatingColumn::make('score')
    ->color('warning')                 // barva plných hvězdiček, výchozí: 'warning'
    ->icons('star', 'outline:star')    // plná, prázdná
```

## Přístupnost

Řada hvězdiček je jediný prvek `role="img"` s popiskem „3 z 5" (přeloženo), takže
čtečka oznámí hodnotu jednou místo předčítání pěti ikon.

## RatingColumn API

```php
->max(int $max)                              // výchozí: 5
->allowHalf(bool $condition = true)
->color(string|Color|null $color)            // výchozí: 'warning'
->icons(string|Icon $filled, string|Icon $empty)
->showValue(bool $condition = true)
->getMax(): int
->isAllowHalf(): bool
```
