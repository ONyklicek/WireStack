---
order: 23
nav: false
---

# TextInputColumn

Inline textový input — validuje a ukládá při blur (nebo enter). Podporuje atribut `type`.

```php
use NyonCode\WireTable\Columns\TextInputColumn;
```

## Základní použití

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->saveOnBlur()
```

## Číselný input

```php
TextInputColumn::make('quantity')
    ->type('number')
    ->rules(['required', 'integer', 'min:0', 'max:9999'])
```

## Emailový input

```php
TextInputColumn::make('email')
    ->type('email')
    ->rules(['required', 'email', 'max:255'])
```

## API TextInputColumn

```php
->type(string $type)                 // 'text', 'number', 'email', 'tel', 'url'
->rules(array|string $rules)         // Laravel validační pravidla
->saveOnBlur(bool $saveOnBlur = true)
->editableUsing(Closure $fn)         // vlastní save callback
```
