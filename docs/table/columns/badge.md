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

```php
BadgeColumn::make('status')
    ->colors([
        'success' => 'active',      // green badge for 'active'
        'danger' => 'banned',       // red badge for 'banned'
        'warning' => 'pending',     // yellow badge for 'pending'
        'gray' => 'draft',          // gray badge for 'draft'
        'primary' => 'featured',    // blue badge for 'featured'
        'info' => 'processing',     // cyan badge for 'processing'
    ])
```

## With Icons

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
        'danger' => 'super_admin',
        'primary' => 'admin',
        'success' => 'editor',
    ])
```

## Size

```php
BadgeColumn::make('tag')
    ->size('xs')     // xs, sm, md, lg
```

## BadgeColumn API

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
