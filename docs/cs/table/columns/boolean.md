---
order: 23
nav: false
---

# BooleanColumn

Zobrazuje hodnoty true/false jako barevné ikony s volitelnými textovými popisky.

```php
use NyonCode\WireTable\Columns\BooleanColumn;
```

## Základní použití

```php
BooleanColumn::make('is_active')
BooleanColumn::make('email_verified_at')   // null = false, non-null = true
```

## Vlastní ikony a barvy

```php
BooleanColumn::make('is_verified')
    ->trueIcon('check-circle')
    ->falseIcon('x-circle')
    ->trueColor('success')
    ->falseColor('danger')
```

## S popisky

```php
BooleanColumn::make('is_published')
    ->labels('Published', 'Draft')
```

## API BooleanColumn

```php
->trueIcon(string|Icon $icon)        // výchozí: 'check-circle'
->falseIcon(string|Icon $icon)       // výchozí: 'x-circle'
->trueColor(string|Color $color)     // výchozí: 'success'
->falseColor(string|Color $color)    // výchozí: 'danger'
->labels(?string $trueLabel, ?string $falseLabel)  // text vedle ikony
```
