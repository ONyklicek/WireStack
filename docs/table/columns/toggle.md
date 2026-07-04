---
order: 23
nav: false
---

# ToggleColumn

Inline toggle switch — saves immediately on click. Dispatches `CellUpdating` / `CellUpdated` events.

```php
use NyonCode\WireTable\Columns\ToggleColumn;
```

## Basic Usage

```php
ToggleColumn::make('is_active')
ToggleColumn::make('is_featured')
```

## Custom Colors

```php
ToggleColumn::make('is_published')
    ->onColor('success')       // green when on
    ->offColor('danger')       // red when off
```

## Custom Icons

```php
ToggleColumn::make('notifications_enabled')
    ->onIcon('bell')
    ->offIcon('bell-slash')
```

## Disabled State

```php
ToggleColumn::make('is_admin')
    ->disabled(fn ($record) => $record->id === auth()->id())  // can't toggle yourself

ToggleColumn::make('is_locked')
    ->disabled()               // always disabled (display only)
```

## ToggleColumn API

```php
->onColor(string $color)             // default: 'primary'
->offColor(string $color)            // default: 'gray'
->onIcon(?string $icon)              // icon when on
->offIcon(?string $icon)             // icon when off
->disabled(bool|Closure $disabled = true)
->isDisabled(Model $record): bool
```
