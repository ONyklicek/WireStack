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

```php
BadgeColumn::make('status')
    ->colors([
        'success' => 'active',      // zelený badge pro 'active'
        'danger' => 'banned',       // červený badge pro 'banned'
        'warning' => 'pending',     // žlutý badge pro 'pending'
        'gray' => 'draft',          // šedý badge pro 'draft'
        'primary' => 'featured',    // modrý badge pro 'featured'
        'info' => 'processing',     // azurový badge pro 'processing'
    ])
```

## S ikonami

```php
BadgeColumn::make('priority')
    ->colors([
        'danger' => 'critical',
        'warning' => 'high',
        'info' => 'medium',
        'gray' => 'low',
    ])
    ->icons([
        'exclamation' => 'critical',
        'arrow-up' => 'high',
        'minus' => 'medium',
        'arrow-down' => 'low',
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
        'danger' => 'super_admin',
        'primary' => 'admin',
        'success' => 'editor',
    ])
```

## Velikost

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg
```

## API BadgeColumn

```php
->colors(array $map)                 // ['color_name' => 'state_value', ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'
->icons(array $map)                  // ['icon_name' => 'state_value', ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'
->size(string $size)                 // 'xs', 'sm', 'md', 'lg'
->getSize(): string
->getColorForState($state): string
->getIconForState($state): ?string
```
