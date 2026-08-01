---
order: 23
nav: false
---

# ColorColumn

Renders a stored CSS color as a swatch next to its literal value. The table-side
counterpart of the infolist `ColorEntry`.

```php
use NyonCode\WireTable\Columns\ColorColumn;
```

## Basic Usage

```php
ColorColumn::make('brand_color')       // "#1a2b3c" → swatch + "#1a2b3c"
```

The state is a CSS color *stored on the record* — hex, `rgb()`, `hsl()`, or a
keyword. It is not a palette name: for palette-driven coloring (a status pill,
a state icon) use [BadgeColumn](badge.md) or [IconColumn](icon.md).

## Swatch Only

Drop the literal value where the column is narrow and the swatch is enough:

```php
ColorColumn::make('brand_color')
    ->swatchOnly()
```

## Copy to Clipboard

The shared `copyable()` API copies the color value:

```php
ColorColumn::make('brand_color')
    ->copyable()
```

## Values it will not draw

A swatch is the one cell that puts record data into a `style` attribute, where
HTML escaping alone is not enough — `;` would open a second declaration. Values
that are not a recognisable CSS color are rejected and the cell falls back to its
empty text:

```php
// Rendered:      #1a2b3c, rgb(255 0 0 / 50%), rebeccapurple
// Not rendered:  "red; background-image: url(…)", "url(…)", "expression(…)"
```

## ColorColumn API

```php
->swatchOnly(bool $condition = true)   // hide the literal value beside the swatch
->isSwatchOnly(): bool
```
