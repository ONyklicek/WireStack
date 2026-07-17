# Placeholder

Static text content display. Does not hold form data.

```php
use NyonCode\WireForms\Components\Display\Placeholder;
```

## Usage

```php
Placeholder::make('notice')
    ->content('This action will send an email to the user.')

Placeholder::make('html_notice')
    ->content('<strong>Important:</strong> changes are irreversible.')
    ->allowHtml()

// Shorthand
Placeholder::make('info')->html('<em>Formatted</em> text')
```

## Methods

| Method | Description |
|--------|-------------|
| `content(string\|Closure)` | Text content |
| `allowHtml()` | Render content as HTML |
| `html(string)` | Set content and enable HTML |
