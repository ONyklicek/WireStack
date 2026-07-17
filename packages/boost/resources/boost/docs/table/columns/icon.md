---
order: 23
nav: false
---

# IconColumn

Displays state-mapped icons with colors and sizes.

```php
use NyonCode\WireTable\Columns\IconColumn;
```

## State-Based Icons

```php
IconColumn::make('status')
    ->icons([
        'active' => 'check-circle',
        'pending' => 'clock',
        'inactive' => 'x-circle',
        'error' => 'exclamation',
    ])
    ->colors([
        'active' => 'success',
        'pending' => 'warning',
        'inactive' => 'danger',     // one entry per state
        'error' => 'danger',
    ])
```

Both maps are keyed by the **state**. A state the map does not mention falls back
to the column's own `->icon()` / `->color()` — to `gray` when no colour is set,
and to no icon when no icon is set.

## Dynamic Resolution

```php
IconColumn::make('health')
    ->iconUsing(fn ($state) => match(true) {
        $state > 80 => 'check-circle',
        $state > 40 => 'minus',
        default => 'exclamation',
    })
    ->colorUsing(fn ($state) => match(true) {
        $state > 80 => 'success',
        $state > 40 => 'warning',
        default => 'danger',
    })
```

## Boolean Mode

```php
IconColumn::make('has_subscription')
    ->boolean()
    ->trueIcon('star')
    ->trueColor('warning')
    ->falseIcon('minus')
    ->falseColor('gray')
```

## Icon Size

```php
IconColumn::make('rating')
    ->iconSize('lg')    // xs, sm, md, lg, xl
```

## IconColumn API

```php
->icons(array $map)                  // ['state_value' => 'icon_name'|Icon, ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'|Icon|null
->colors(array $map)                 // ['state_value' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'|Color|null
->iconSize(string $size)             // 'xs', 'sm', 'md', 'lg', 'xl'
->boolean(string|Icon $trueIcon = 'check-circle', string|Icon $falseIcon = 'x-circle')  // enable boolean mode
->trueIcon(string|Icon|null $icon)
->falseIcon(string|Icon $icon)
->trueColor(string|Color $color)
->falseColor(string|Color $color)
->booleanColors(string|Color $true = 'success', string|Color $false = 'danger')
```
