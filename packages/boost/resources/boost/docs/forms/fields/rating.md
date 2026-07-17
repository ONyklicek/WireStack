# Rating

Star rating field with configurable max stars, half-star precision, and colour.

```php
use NyonCode\WireForms\Components\Rating;
```

## Basic Usage

```php
Rating::make('score')
    ->max(5)
    ->default(0)
```

## Half Stars

```php
Rating::make('rating')
    ->allowHalf()    // allows 0.5 increments: 1, 1.5, 2, 2.5 …
```

## Custom Max

```php
Rating::make('priority')
    ->max(3)         // 3-star scale
```

## Colours

```php
Rating::make('satisfaction')
    ->color('primary')   // any palette color ('primary' is the default)
```

## Non-Clearable

```php
Rating::make('score')
    ->clearable(false)   // clicking the active star no longer resets it
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `max(int)` | int | Number of stars (default `5`) |
| `allowHalf(bool)` | bool | Enable half-star selection (default `false`) |
| `color(string)` | string | Filled-star colour — any palette color (see [Colors](../../core/foundation.md#colors)) |
| `clearable(bool)` | bool | Click active star to reset to 0 (default `true`) |
| `default(int\|float\|Closure)` | number | Pre-filled value |
| `disabled(bool\|Closure)` | bool | Disable the rating |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on click |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
