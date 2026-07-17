---
order: 23
nav: false
---

# ImageColumn

Zobrazuje obrázky/avatary v buňkách tabulky. Hodnota typu pole se vykreslí jako galerie.

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

## Galerie

Hodnota typu pole (včetně JSON pole, jak ho dodá sloupec s `array` castem)
vykreslí všechny obrázky. `stacked()` je překryje jako avatary, `stackLimit()`
omezí, kolik se jich vykreslí, a zbytek shrne do čipu `+N`.

```php
ImageColumn::make('members')
    ->circular()
    ->stacked()                 // překryv s oddělujícím prstencem
    ->stackLimit(3)             // vykreslí 3, zbytek jako „+N" (výchozí 3)
```

`stackLimit()` platí jen pro stack — galerie bez `stacked()` se zalomí a ukáže vše.

## Neveřejné soubory

Ve výchozím stavu sloupec staví běžnou Storage URL. Pro soubor, který není
veřejně čitelný, si nech od disku podepsat dočasnou:

```php
ImageColumn::make('scan')
    ->disk('s3')
    ->visibility('private')     // podepsat dočasnou URL
    ->urlExpiry(30)             // minuty platnosti podepsané URL (výchozí 5)
```

> Podepsat URL neumí každý driver — `local` spadne, dokud není zaregistrovaná
> Laravelí temporary-url routa. Sloupec v takovém případě spadne zpátky na
> běžnou URL, aby jeden nepodepsatelný obrázek nerozbil celou tabulku.

## API ImageColumn

```php
->size(string|Closure $size)          // škála: xs | sm | md | lg | xl | 2xl (výchozí md)
->circular(bool $circular = true)     // rounded-full (jinak rounded-md)
->defaultImageUrl(?string $url)       // fallback obrázek, když je hodnota prázdná
->disk(?string $disk)                 // resolvovat relativní cesty přes Storage disk
->ring(int $ring)                 // šířka prstence avataru
->stacked(bool $stacked = true)       // překryje hodnotu typu pole jako avatary
->stackLimit(int $limit)              // počet obrázků před čipem „+N" (výchozí 3)
->visibility(?string $visibility)     // 'public' (výchozí), jinak => dočasná URL
->urlExpiry(int $minutes)             // platnost podepsané URL (výchozí 5)
```

> `size()` bere pojmenovanou škálu, ne pixely — škála mapuje na Tailwind
> width/height utility (`md` → `w-10 h-10`). Jeho signatura odpovídá kanonickému
> `HasSize::size(string|Closure)`, takže sloupec zůstává použitelný; předání
> neznámé hodnoty spadne zpět na škálu `md`.
