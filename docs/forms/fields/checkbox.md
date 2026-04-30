# Checkbox

Single checkbox for boolean values.

```php
use NyonCode\WireForms\Components\Checkbox;
```

## Usage

```php
Checkbox::make('agree_terms')
    ->label('I agree to the terms')
    ->default(false)
    ->description('You must agree before continuing')
    ->inline()
```

## Methods

| Method | Description |
|--------|-------------|
| `description(string)` | Help text below the checkbox |
| `inline()` | Inline layout (label next to checkbox) |
