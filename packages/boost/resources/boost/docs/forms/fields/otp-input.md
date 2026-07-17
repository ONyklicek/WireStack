# OtpInput

OTP / PIN input — N separate character boxes with automatic focus advance, paste support, and arrow-key navigation.

```php
use NyonCode\WireForms\Components\OtpInput;
```

## Basic Usage

```php
OtpInput::make('code')
    ->length(6)
```

Stored value is a plain string: `'123456'`.

## Digits Only

```php
OtpInput::make('pin')
    ->length(4)
    ->numericOnly()    // inputmode="numeric", accepts 0-9 only
```

## Masked (Password Style)

```php
OtpInput::make('pin')
    ->length(4)
    ->masked()
```

## Visual Separator

```php
OtpInput::make('code')
    ->length(6)
    ->separator(3)    // renders as: [x][x][x] — [x][x][x]
```

## Verification Code Pattern

```php
OtpInput::make('verification_code')
    ->length(6)
    ->numericOnly()
    ->required()
    ->live()
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `length(int)` | int | Number of individual input boxes (default `6`) |
| `numericOnly(bool)` | bool | Accept digits 0–9 only |
| `masked(bool)` | bool | Mask characters like a password field |
| `separator(int)` | int | Show a dash separator every N characters |
| `disabled(bool\|Closure)` | bool | Disable all boxes |
| `readOnly(bool\|Closure)` | bool | Read-only mode |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on every change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
