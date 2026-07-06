---
order: 23
nav: false
---

# ImageColumn

Zobrazuje obrázky/avatary v buňkách tabulky.

```php
use NyonCode\WireTable\Columns\ImageColumn;
```

## Základní použití

```php
ImageColumn::make('avatar_url')
    ->circular()
    ->size('md')

ImageColumn::make('photo')
    ->size('lg')
    ->defaultImageUrl('/images/placeholder.png')
```

## API ImageColumn

```php
->size(string|Closure $size)          // škála: xs | sm | md | lg | xl | 2xl (výchozí md)
->circular(bool $circular = true)     // rounded-full (jinak rounded-md)
->defaultImageUrl(?string $url)       // fallback obrázek, když je hodnota prázdná
->disk(?string $disk)                 // resolvovat relativní cesty přes Storage disk
->ring(int $ring, ?int $color = null) // šířka prstence avataru
```

> `size()` bere pojmenovanou škálu, ne pixely — škála mapuje na Tailwind
> width/height utility (`md` → `w-10 h-10`). Jeho signatura odpovídá kanonickému
> `HasSize::size(string|Closure)`, takže sloupec zůstává použitelný; předání
> neznámé hodnoty spadne zpět na škálu `md`.
