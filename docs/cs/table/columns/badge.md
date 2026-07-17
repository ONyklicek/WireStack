---
order: 23
nav: false
---

# BadgeColumn

Zobrazení barevného badge/tagu s mapováním barvy a ikony podle stavu.

```php
use NyonCode\WireTable\Columns\BadgeColumn;
```

## Základní použití

Mapa je klíčovaná **stavem**, hodnota je barva, kterou pro něj badge dostane:

```php
BadgeColumn::make('status')
    ->colors([
        'active' => 'success',      // zelený badge pro 'active'
        'banned' => 'danger',       // červený badge pro 'banned'
        'pending' => 'warning',     // žlutý badge pro 'pending'
        'draft' => 'gray',          // šedý badge pro 'draft'
        'featured' => 'primary',    // modrý badge pro 'featured'
        'processing' => 'info',     // azurový badge pro 'processing'
    ])
```

Stav, který mapa neuvádí, spadne zpět na vlastní `->color()` sloupce, a když ani
ten není nastavený, na `gray`. Hodnoty jde zapsat i enumem `Color`
(`'active' => Color::Success`).

## S ikonami

`->icons()` je klíčovaná stavem úplně stejně a stav, který mapa neuvádí, spadne
zpět na vlastní `->icon()` sloupce — když ani ten není nastavený, ikona se
nezobrazí. Enum stav implementující kontrakt `HasIcon` si ikonu vybere sám i bez
mapy.

```php
BadgeColumn::make('priority')
    ->colors([
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        'low' => 'gray',
    ])
    ->icons([
        'critical' => 'exclamation',
        'high' => 'arrow-up',
        'medium' => 'minus',
        'low' => 'arrow-down',
    ])
```

## Dynamické barvy

```php
// Resolvování barvy pomocí closury
BadgeColumn::make('score')
    ->colorUsing(fn (int $state) => match(true) {
        $state >= 90 => 'success',
        $state >= 70 => 'info',
        $state >= 50 => 'warning',
        default => 'danger',
    })
    ->iconUsing(fn (int $state) => $state >= 90 ? 'star' : null)
```

## Vlastní popisek + badge

```php
BadgeColumn::make('role')
    ->formatStateUsing(fn (string $state) => match($state) {
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'editor' => 'Editor',
        default => ucfirst($state),
    })
    ->colors([
        'super_admin' => 'danger',
        'admin' => 'primary',
        'editor' => 'success',
    ])
```

## Velikost

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg
```

## API BadgeColumn

```php
->colors(array $map)                 // ['state_value' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'|Color|null
->icons(array $map)                  // ['state_value' => 'icon_name'|Icon, ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'|Icon|null
->size(string $size)                 // 'xs', 'sm', 'md', 'lg'
->getSize(): string
->getColorForState($state): ?string
->getIconForState($state): ?string
```
