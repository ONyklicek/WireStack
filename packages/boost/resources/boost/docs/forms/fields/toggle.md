# Toggle

Toggle switch for boolean values with customizable appearance.

```php
use NyonCode\WireForms\Components\Toggle;
```

## Usage

```php
Toggle::make('is_active')
    ->label('Active')
    ->default(true)
```

## Customization

```php
Toggle::make('notifications_enabled')
    ->onLabel('On')
    ->offLabel('Off')
    ->onColor('success')
    ->offColor('danger')
    ->onIcon('check')
    ->offIcon('x')
    ->inline()
```

## Live Updates

```php
Toggle::make('dark_mode')
    ->live()    // immediately re-renders the form on change
```

## Conditional Disable

```php
Toggle::make('published')
    ->disabled(fn () => ! auth()->user()->can('publish'))
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `onLabel(string\|Closure\|null)` | string | Label shown when toggled on |
| `offLabel(string\|Closure\|null)` | string | Label shown when toggled off |
| `onColor(string\|Color)` | string | Color when on — any palette color (see [Colors](../../core/foundation.md#colors)) |
| `offColor(string\|Color)` | string | Color when off |
| `onIcon(string\|Icon\|null)` | string | Icon when on |
| `offIcon(string\|Icon\|null)` | string | Icon when off |
| `inline(bool)` | bool | Display label inline with the toggle (default `true`) |
| `default(bool\|Closure)` | bool | Pre-filled value |
| `disabled(bool\|Closure)` | bool | Disable the toggle |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
