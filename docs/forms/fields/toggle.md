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
Toggle::make('is_active')
    ->onLabel('Active')
    ->offLabel('Inactive')
    ->onColor('success')
    ->offColor('danger')
    ->onIcon('check')
    ->offIcon('x')
    ->inline()
```

## Methods

| Method | Description |
|--------|-------------|
| `onLabel(string)` | Label when on |
| `offLabel(string)` | Label when off |
| `onColor(string)` | Color when on (success, primary, etc.) |
| `offColor(string)` | Color when off |
| `onIcon(string)` | Icon when on |
| `offIcon(string)` | Icon when off |
| `inline()` | Inline layout |
