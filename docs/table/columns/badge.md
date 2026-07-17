---
order: 23
nav: false
---

# BadgeColumn

Colored badge/tag display with state-based color and icon mapping.

```php
use NyonCode\WireTable\Columns\BadgeColumn;
```

## Basic Usage

The map is keyed by the **state**, and each value is the colour to wear for it:

```php
BadgeColumn::make('status')
    ->colors([
        'active' => 'success',      // green badge for 'active'
        'banned' => 'danger',       // red badge for 'banned'
        'pending' => 'warning',     // yellow badge for 'pending'
        'draft' => 'gray',          // gray badge for 'draft'
        'featured' => 'primary',    // blue badge for 'featured'
        'processing' => 'info',     // cyan badge for 'processing'
    ])
```

A state the map does not mention falls back to the column's own `->color()`, and
to `gray` when that is unset. Values may also be given as the `Color` enum
(`'active' => Color::Success`).

## With Icons

`->icons()` is keyed by the state the same way, and falls back to the column's
own `->icon()` when the state maps to nothing — an unset `->icon()` means no
icon. An enum state implementing the `HasIcon` contract picks its own icon with
no map at all.

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

## Dynamic Colors

```php
// Closure-based color resolution
BadgeColumn::make('score')
    ->colorUsing(fn (int $state) => match(true) {
        $state >= 90 => 'success',
        $state >= 70 => 'info',
        $state >= 50 => 'warning',
        default => 'danger',
    })
    ->iconUsing(fn (int $state) => $state >= 90 ? 'star' : null)
```

## Custom Label + Badge

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

## Size

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg
```

## BadgeColumn API

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
