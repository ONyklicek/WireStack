# ViewField

Renders a custom Blade view inside the form.

```php
use NyonCode\WireForms\Components\Display\ViewField;
```

## Usage

```php
ViewField::make('preview')
    ->view('components.order-preview')
    ->viewData(['key' => 'value'])
```

## Methods

| Method | Description |
|--------|-------------|
| `view(string)` | Blade view name |
| `viewData(array)` | Data passed to the view |
| `content(string)` | Static content (alternative to view) |
| `escape()` | HTML-escape the content |
