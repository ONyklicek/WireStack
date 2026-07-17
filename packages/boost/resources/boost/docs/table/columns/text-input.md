---
order: 23
nav: false
---

# TextInputColumn

Inline text input — validates and saves on blur (or enter).

```php
use NyonCode\WireTable\Columns\TextInputColumn;
```

## Basic Usage

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->saveOnBlur()
```

## Input Types

`type()` sets the HTML input type directly; the shorthands below are the common
ones, and some also set a sensible `step`.

```php
TextInputColumn::make('quantity')->numeric()      // type="number"
TextInputColumn::make('quantity')->integer()      // type="number", step="1"
TextInputColumn::make('rate')->decimal()          // type="number", step="0.01"
TextInputColumn::make('rate')->decimal(3)         // type="number", step="0.001"

TextInputColumn::make('email')->email()           // type="email"
TextInputColumn::make('phone')->tel()             // type="tel"
TextInputColumn::make('website')->url()           // type="url"
TextInputColumn::make('secret')->password()       // type="password"

TextInputColumn::make('code')->type('search')     // any type you need
```

## Formatted Numbers

`money()` displays the value with separators and parses it back to a number on
save. The input stays `type="text"` — a native number input would reject the
formatted string.

```php
TextInputColumn::make('total')
    ->money()                       // 1234567.5  ->  "1 234 568"
    ->money(2)                      // 1234567.5  ->  "1 234 567,50"
    ->money(2, ',', '.')            // 1234567.5  ->  "1,234,567.50"

TextInputColumn::make('total')->czk(2)   // alias: money() with the Czech format
```

Typing `1 234,50` saves `1234.5`: the thousands separator is stripped, the
decimal separator becomes a dot, and any other character is discarded.

## Validation

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])   // replaces the rule list
    ->rule('alpha_dash')                         // appends one rule
    ->required()                                 // shorthand: appends 'required'
    ->validationMessages(['max' => 'Too long!'])
    ->validationAttribute('full name')           // name used in messages
```

Rules may also be a Closure receiving the record:

```php
TextInputColumn::make('discount')
    ->rules(fn ($record) => $record->is_vip ? ['numeric', 'max:50'] : ['numeric', 'max:10'])
```

> `required(false)` does **not** remove a `required` rule — it simply adds
> nothing. Leave the call off, or manage the list with `rules()`.

Validation normally runs on save. To validate as the user types:

```php
TextInputColumn::make('name')->liveValidation()            // 500ms debounce
TextInputColumn::make('name')->liveValidation(debounce: 150)
```

### Native Constraints

Rendered as attributes on the input, so the browser enforces them too. They do
not replace `rules()` — a forged request bypasses the browser.

```php
TextInputColumn::make('quantity')
    ->min('0')->max('9999')      // number/date bounds
    ->step('0.5')
    ->minLength(3)->maxLength(255)
    ->pattern('[A-Z]{3}-[0-9]+')
```

## Saving

```php
TextInputColumn::make('name')
    ->saveOnBlur()               // save when the field loses focus
    ->saveOnEnter()              // save on Enter
```

Replace persistence entirely with `saveUsing()` — the callback receives the
record, the new value and the column, and is responsible for saving:

```php
TextInputColumn::make('name')
    ->saveUsing(fn ($record, $value, $column) => $record->forceFill(['name' => $value])->saveQuietly())
```

React to a successful save with `afterStateUpdated()`:

```php
TextInputColumn::make('quantity')
    ->afterStateUpdated(fn ($record, $value) => $record->order->recalculateTotal())
```

## Transforming Values

`beforeSave()` runs last, after the built-in transforms:

```php
TextInputColumn::make('code')
    ->trim()                     // strip surrounding whitespace
    ->nullable()                 // store '' as null
    ->uppercase()                // or ->lowercase()
    ->beforeSave(fn ($value, $record) => str_replace(' ', '-', $value))
```

The save pipeline runs in this order: **trim → nullable → number parsing
(`money()`) → uppercase/lowercase → `beforeSave()`**.

Going the other way, `afterLoad()` shapes the value put *into* the input, and
`displayFormat()` shapes the read-only text shown when the cell is not editable:

```php
TextInputColumn::make('phone')
    ->afterLoad(fn ($value, $record) => $value ? '+420 '.$value : '')
    ->displayFormat(fn ($value, $record) => $value ?: '—')
```

## Input Appearance

```php
TextInputColumn::make('price')
    ->inputPrefix('$')
    ->inputSuffix('.00')
    ->helperText('Net price, excluding VAT')
    ->inputClass('font-mono text-right')
    ->autocomplete('off')
    ->autofocus()
```

## Access Control

```php
TextInputColumn::make('name')
    ->disabled(fn ($record) => $record->is_locked)   // bool or Closure
    ->readonly()                                     // bool or Closure
    ->editPermission('orders.edit')
```

`editPermission()` is enforced server-side on every save, not just in the UI. It
resolves against the authenticated user via `hasPermissionTo()` or `can()`, and a
user holding the `Super Admin` role always passes.

## TextInputColumn API

```php
// Type
->type(string $type)                       // raw HTML input type
->numeric()                                // type="number"
->integer()                                // type="number", step="1"
->decimal(int $places = 2)                 // type="number", step derived from $places
->email() / ->tel() / ->url() / ->password()

// Formatted numbers
->money(int $decimals = 0, string $thousandsSeparator = ' ', string $decimalSeparator = ',')
->czk(int $decimals = 0)                   // alias for money() with the Czech format

// Validation
->rules(array|Closure $rules)              // Laravel rules; a Closure receives the record
->rule(string $rule)                       // append a single rule
->required(bool $required = true)          // appends 'required'; false is a no-op
->validationMessages(array $messages)
->validationAttribute(string $attribute)
->liveValidation(bool $live = true, int $debounce = 500)

// Native input constraints
->min(?string $min) / ->max(?string $max)
->minLength(?int $length) / ->maxLength(?int $length)
->pattern(?string $pattern)
->step(?string $step)

// Saving
->saveOnBlur(bool $save = true)
->saveOnEnter(bool $save = true)
->saveUsing(?Closure $callback)            // fn($record, $value, $column) — replaces persistence
->afterStateUpdated(?Closure $callback)    // fn($record, $value) — after a successful save
->editableUsing(Closure $callback)         // inherited from Column

// Value transformation
->trim(bool $trim = true)
->nullable(bool $nullable = true)          // '' becomes null
->uppercase(bool $uppercase = true) / ->lowercase(bool $lowercase = true)
->beforeSave(Closure $formatter)           // fn($value, $record) — runs last before saving
->afterLoad(Closure $formatter)            // fn($value, $record) — shapes the input value
->displayFormat(Closure $formatter)        // fn($value, $record) — read-only display

// Appearance
->inputPrefix(?string $prefix) / ->inputSuffix(?string $suffix)
->helperText(?string $text)
->inputClass(?string $class)
->autocomplete(?string $autocomplete)
->autofocus(bool $autofocus = true)

// Access control
->disabled(bool|Closure $disabled = true)
->readonly(bool|Closure $readonly = true)
->editPermission(?string $permission)      // enforced server-side on save
```
