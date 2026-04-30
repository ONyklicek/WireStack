# ColorPicker

Color picker with format selection.

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
