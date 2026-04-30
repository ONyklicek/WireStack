# TextInput

Text input field with variants for email, password, numeric, tel, and URL.

```php
use NyonCode\WireForms\Components\TextInput;
```

## Basic Usage

```php
TextInput::make('name')
TextInput::make('email')->email()
TextInput::make('password')->password()
TextInput::make('phone')->tel()
TextInput::make('website')->url()
TextInput::make('quantity')->numeric()
TextInput::make('age')->integer()
```

## Type Variants

| Method | HTML type | Description |
|--------|-----------|-------------|
| `email()` | `email` | Email validation hint |
| `password()` | `password` | Masked input |
| `tel()` | `tel` | Phone number |
| `url()` | `url` | URL input |
| `numeric()` | `text` with inputmode | Numeric with decimal |
| `integer()` | `text` with inputmode | Integer only |
| `type(string)` | Custom | Set HTML input type directly |

## Constraints

```php
TextInput::make('code')
    ->minLength(3)
    ->maxLength(10)
    ->minValue(0)
    ->maxValue(100)
    ->step('0.01')
    ->mask('999-999-999')
    ->inputMode('numeric')
    ->autocomplete('off')
```

## Decorators

```php
TextInput::make('price')
    ->prefix('CZK')
    ->suffix('.00')
    ->prefixIcon('currency')
    ->suffixIcon('calculator')
```

## Revealable Password

```php
TextInput::make('password')
    ->password()
    ->revealable()    // toggle visibility button
```

## Datalist

```php
TextInput::make('city')
    ->datalist(['Prague', 'Brno', 'Ostrava'])
```

## Live Updates

```php
TextInput::make('search')
    ->live()
    ->debounce(300)
```
