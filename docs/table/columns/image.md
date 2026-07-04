---
order: 23
nav: false
---

# ImageColumn

Displays images/avatars in table cells.

```php
use NyonCode\WireTable\Columns\ImageColumn;
```

## Basic Usage

```php
ImageColumn::make('avatar_url')
    ->circular()
    ->size('md')

ImageColumn::make('photo')
    ->size('lg')
    ->defaultImageUrl('/images/placeholder.png')
```

## ImageColumn API

```php
->size(string|Closure $size)          // scale: xs | sm | md | lg | xl | 2xl (default md)
->circular(bool $circular = true)     // rounded-full (otherwise rounded-md)
->defaultImageUrl(?string $url)       // fallback image when the value is empty
->disk(?string $disk)                 // resolve relative paths via a Storage disk
->ring(int $ring, ?int $color = null) // avatar ring width
```

> `size()` takes a named scale, not pixels — the scale maps to Tailwind
> width/height utilities (`md` → `w-10 h-10`). Its signature matches the
> canonical `HasSize::size(string|Closure)` so the column stays usable; passing
> an unknown value falls back to the `md` scale.
