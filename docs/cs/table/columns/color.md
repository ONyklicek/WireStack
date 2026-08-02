---
order: 23
nav: false
---

# ColorColumn

Vykreslí uloženou CSS barvu jako vzorník vedle její textové hodnoty. Tabulkový
protějšek `ColorEntry` z infolistu.

```php
use NyonCode\WireTable\Columns\ColorColumn;
```

## Základní použití

```php
ColorColumn::make('brand_color')       // "#1a2b3c" → vzorník + "#1a2b3c"
```

Stavem je CSS barva *uložená u záznamu* — hex, `rgb()`, `hsl()` nebo klíčové
slovo. Není to název z palety: pro barvy řízené paletou (stavová pilulka, ikona
podle stavu) použijte [BadgeColumn](badge.md) nebo [IconColumn](icon.md).

## Jen vzorník

Tam, kde je sloupec úzký a vzorník stačí, textovou hodnotu vynechte:

```php
ColorColumn::make('brand_color')
    ->swatchOnly()
```

## Kopírování do schránky

Sdílené `copyable()` zkopíruje hodnotu barvy:

```php
ColorColumn::make('brand_color')
    ->copyable()
```

## Hodnoty, které se nevykreslí

Vzorník je jediná buňka, která vkládá data záznamu do atributu `style`, kde
escapování HTML nestačí — `;` by otevřelo další deklaraci. Hodnoty, které nejsou
rozpoznatelná CSS barva, se odmítnou a buňka zobrazí svůj prázdný text:

```php
// Vykreslí se:      #1a2b3c, rgb(255 0 0 / 50%), rebeccapurple
// Nevykreslí se:    "red; background-image: url(…)", "url(…)", "expression(…)"
```

## ColorColumn API

```php
->swatchOnly(bool $condition = true)   // skryje textovou hodnotu vedle vzorníku
->isSwatchOnly(): bool
```
