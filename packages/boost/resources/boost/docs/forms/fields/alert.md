# Alert

Alert/callout box for displaying messages.

```php
use NyonCode\WireForms\Components\Display\Alert;
```

## Usage

```php
Alert::make('warning')
    ->title('Warning')
    ->message('This action cannot be undone.')
    ->warning()
    ->icon('exclamation')
    ->dismissible()
```

## Color Helpers

```php
Alert::make('x')->info()       // blue
Alert::make('x')->success()    // green
Alert::make('x')->warning()    // yellow
Alert::make('x')->danger()     // red
Alert::make('x')->color('primary')  // explicit color
```

## Methods

| Method | Description |
|--------|-------------|
| `title(string)` | Alert title |
| `message(string)` / `content(string)` | Alert body |
| `icon(string)` | Icon name |
| `color(string)` | Alert color |
| `info()` / `success()` / `warning()` / `danger()` | Color shortcuts |
| `dismissible()` | Allow dismissing |
