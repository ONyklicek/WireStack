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

## Conditional Visibility

```php
Checkbox::make('receive_newsletter')
    ->label('Subscribe to newsletter')
    ->visible(fn () => $this->email !== null)
```

## Disabled State

```php
Checkbox::make('is_verified')
    ->label('Email verified')
    ->disabled()
    ->default(fn () => $this->record?->email_verified_at !== null)
```

## Live Updates

```php
Checkbox::make('show_advanced')
    ->live()    // re-renders the form when toggled
```

## Methods

| Method | Type | Description |
|--------|------|-------------|
| `description(string\|Closure\|null)` | string | Help text rendered below the checkbox |
| `inline(bool)` | bool | Display label inline next to the checkbox |
| `default(mixed\|Closure)` | mixed | Pre-filled value (`true` or `false`) |
| `disabled(bool\|Closure)` | bool | Disable the checkbox |
| `readOnly(bool\|Closure)` | bool | Make the checkbox read-only |
| `required()` | — | Mark as required |
| `live()` | — | Trigger Livewire update on change |

See [Common Field API](index.md#common-field-api) for label, hint, tooltip, and other shared methods.
