---
order: 23
nav: false
---

# IconColumn

Zobrazuje ikony mapované podle stavu s barvami a velikostmi.

```php
use NyonCode\WireTable\Columns\IconColumn;
```

## Ikony podle stavu

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
        'inactive' => 'danger',     // jeden záznam na každý stav
        'error' => 'danger',
    ])
```

Obě mapy jsou klíčované **stavem**. Stav, který mapa neuvádí, spadne zpět na
vlastní `->icon()` / `->color()` sloupce — bez nastavené barvy na `gray`, bez
nastavené ikony se ikona nezobrazí.

## Dynamické resolvování

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

## Boolean režim

```php
IconColumn::make('has_subscription')
    ->boolean()
    ->trueIcon('star')
    ->trueColor('warning')
    ->falseIcon('minus')
    ->falseColor('gray')
```

## Velikost ikony

```php
IconColumn::make('rating')
    ->iconSize('lg')    // xs, sm, md, lg, xl
```

## API IconColumn

```php
->icons(array $map)                  // ['state_value' => 'icon_name'|Icon, ...]
->iconUsing(Closure $fn)             // fn($state) => 'icon_name'|Icon|null
->colors(array $map)                 // ['state_value' => 'color_name'|Color, ...]
->colorUsing(Closure $fn)            // fn($state) => 'color_name'|Color|null
->iconSize(string $size)             // 'xs', 'sm', 'md', 'lg', 'xl'
->boolean(string|Icon $trueIcon = 'check-circle', string|Icon $falseIcon = 'x-circle')  // zapnout boolean režim
->trueIcon(string|Icon|null $icon)
->falseIcon(string|Icon $icon)
->trueColor(string|Color $color)
->falseColor(string|Color $color)
->booleanColors(string|Color $true = 'success', string|Color $false = 'danger')
```
