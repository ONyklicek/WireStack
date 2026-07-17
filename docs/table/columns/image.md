---
order: 23
nav: false
---

# ImageColumn

Displays images/avatars in table cells. An array state renders as a gallery.

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

## Galleries

An array state (including a JSON array, as an `array`-cast column delivers it)
renders every image. `stacked()` overlaps them like avatars; `stackLimit()` caps
how many are drawn and summarises the rest as a `+N` chip.

```php
ImageColumn::make('members')
    ->circular()
    ->stacked()                 // overlap, with a separating ring
    ->stackLimit(3)             // draw 3, then "+N" for the rest (default 3)
```

`stackLimit()` only applies to a stack — an unstacked gallery wraps and shows
everything.

## Private Files

By default the column builds a plain Storage URL. For a file that is not
publicly readable, ask the disk to sign a temporary one:

```php
ImageColumn::make('scan')
    ->disk('s3')
    ->visibility('private')     // sign a temporary URL
    ->urlExpiry(30)             // minutes the signed URL stays valid (default 5)
```

> Not every driver can sign a URL — the `local` driver throws unless Laravel's
> temporary-url route is registered. The column falls back to the plain URL in
> that case, so one unsignable image cannot break the table.

## ImageColumn API

```php
->size(string|Closure $size)          // scale: xs | sm | md | lg | xl | 2xl (default md)
->circular(bool $circular = true)     // rounded-full (otherwise rounded-md)
->defaultImageUrl(?string $url)       // fallback image when the value is empty
->disk(?string $disk)                 // resolve relative paths via a Storage disk
->ring(int $ring)                 // avatar ring width
->stacked(bool $stacked = true)       // overlap an array state like avatars
->stackLimit(int $limit)              // images drawn before the "+N" chip (default 3)
->visibility(?string $visibility)     // 'public' (default) or anything else => temporary URL
->urlExpiry(int $minutes)             // signed-url lifetime (default 5)
```

> `size()` takes a named scale, not pixels — the scale maps to Tailwind
> width/height utilities (`md` → `w-10 h-10`). Its signature matches the
> canonical `HasSize::size(string|Closure)` so the column stays usable; passing
> an unknown value falls back to the `md` scale.
