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
| `numeric()` | `number` with inputmode | Numeric with decimal |
| `integer()` | `number` with inputmode | Integer only, step=1 |
| `search()` | `search` | Search input |
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

## Affix and hint actions

Place an interactive `Action` before/after the input or next to the hint. The callback runs on the
server with the same reactive `$get` / `$set` context as [`afterStateUpdated()`](../reactive-fields.md#field-actions-and-buttons)
— use it for lookups (ARES, address verification), generating a value from another field, or an
inline action:

```php
use NyonCode\WireCore\Actions\Action;

TextInput::make('company')
    ->suffixAction(
        Action::make('lookup')
            ->icon('heroicon-o-magnifying-glass')
            ->action(fn ($get, $set) => $set('company', lookupCompany($get('company')))),
    )
    ->hintAction(
        Action::make('help')->icon('heroicon-o-question-mark-circle'),
    );
```

`prefixAction()`, `suffixAction()` and `hintAction()` each take an `Action` and share the field's
state context. For a standalone button, use the [`Button`](button.md) field.

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

Pass a PHP enum class to use its case labels as the suggestions (same label resolution as
[`Select` options](select.md#enum-options)):

```php
TextInput::make('city')->datalist(City::class)
```

## Live Updates

```php
TextInput::make('search')
    ->live()
    ->debounce(300)
```

## Validation

```php
TextInput::make('username')
    ->required()
    ->rules(['alpha_dash', 'min:3', 'max:30'])
    ->validationMessages(['required' => 'Username is required'])
```

## Common Options

```php
TextInput::make('bio')
    ->label('Short bio')
    ->helperText('Displayed on your profile')
    ->hint('Max 255 chars')
    ->placeholder('Tell us about yourself')
    ->disabled(fn () => $this->locked)
    ->readOnly(fn () => ! auth()->user()->canEdit())
    ->autofocus()
```

See [Common Field API](index.md#common-field-api) for the full list of shared methods.
