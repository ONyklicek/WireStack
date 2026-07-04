---
order: 23
nav: false
---

# TextInputColumn

Inline text input — validates and saves on blur (or enter). Supports `type` attribute.

```php
use NyonCode\WireTable\Columns\TextInputColumn;
```

## Basic Usage

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->saveOnBlur()
```

## Number Input

```php
TextInputColumn::make('quantity')
    ->type('number')
    ->rules(['required', 'integer', 'min:0', 'max:9999'])
```

## Email Input

```php
TextInputColumn::make('email')
    ->type('email')
    ->rules(['required', 'email', 'max:255'])
```

## TextInputColumn API

```php
->type(string $type)                 // 'text', 'number', 'email', 'tel', 'url'
->rules(array|string $rules)         // Laravel validation rules
->saveOnBlur(bool $saveOnBlur = true)
->editableUsing(Closure $fn)         // custom save callback
```
