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
        'check-circle' => 'active',
        'clock' => 'pending',
        'x-circle' => 'inactive',
        'exclamation' => 'error',
    ])
    ->colors([
        'success' => 'active',
        'warning' => 'pending',
        'danger' => ['inactive', 'error'],  // multiple states → one color
    ])
```

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
->icons(array $map)                  // ['icon_name' => 'state_value', ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'
->colors(array $map)                 // ['color_name' => 'state_value'|['values'], ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'
->iconSize(string $size)             // 'xs', 'sm', 'md', 'lg', 'xl'
->boolean(string|Icon $trueIcon = 'check-circle', string|Icon $falseIcon = 'x-circle')  // enable boolean mode
->trueIcon(string|Icon|null $icon)
->falseIcon(string|Icon $icon)
->trueColor(string|Color $color)
->falseColor(string|Color $color)
->booleanColors(string|Color $true = 'success', string|Color $false = 'danger')
```
