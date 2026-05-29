# ColorPicker

Color picker with format selection and optional swatches.

```php
use NyonCode\WireForms\Components\ColorPicker;
```

## Usage

```php
ColorPicker::make('brand_color')
    ->hex()        // #RRGGBB (default)

ColorPicker::make('bg')
    ->hsl()        // hsl(h, s%, l%)

ColorPicker::make('overlay')
    ->rgba()       // rgba(r, g, b, a)

ColorPicker::make('text')
    ->rgb()        // rgb(r, g, b)
```

## Explicit Format

```php
ColorPicker::make('color')
    ->format('hex')    // 'hex', 'hsl', 'rgb', 'rgba'
```

## Swatches

Provide a list of predefined colours the user can click to select instantly.

```php
ColorPicker::make('brand_color')
    ->swatches(['#ef4444', '#f97316', '#eab308', '#22c55e', '#3b82f6', '#a855f7'])
```

Dynamic swatches from a closure:

```php
ColorPicker::make('theme_color')
    ->swatches(fn () => $this->record->team->allowed_colors)
```

## Live Updates

```php
ColorPicker::make('preview_color')
    ->live()    // update on every change for real-time preview
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `hex()` | — | Store as `#RRGGBB` (default) |
| `hsl()` | — | Store as `hsl(h, s%, l%)` |
| `rgb()` | — | Store as `rgb(r, g, b)` |
| `rgba()` | — | Store as `rgba(r, g, b, a)` |
| `format(string)` | string | Explicit format: `hex`, `hsl`, `rgb`, `rgba` |
| `swatches(array\|Closure)` | array | Predefined hex colours shown as clickable swatches |
| `default(string\|Closure)` | string | Pre-filled colour value |
| `disabled(bool\|Closure)` | bool | Disable the picker and swatches |
| `live()` | — | Trigger Livewire update on every change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
