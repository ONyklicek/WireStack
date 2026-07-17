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

## Common Options

`Hidden` supports the full Field API for validation and conditional logic even though it renders no UI:

```php
Hidden::make('status')
    ->default('draft')
    ->rules(['in:draft,published,archived'])
```

See [Common Field API](index.md#common-field-api) for all shared methods.
