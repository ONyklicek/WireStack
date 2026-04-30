# Hidden

Hidden input field. Automatically hidden from the form — no label, no wrapper.

```php
use NyonCode\WireForms\Components\Hidden;
```

## Usage

```php
Hidden::make('user_id')
    ->default(fn () => auth()->id())

Hidden::make('type')
    ->default('post')
```

No additional methods beyond the base Field API.
