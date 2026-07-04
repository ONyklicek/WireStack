---
order: 23
nav: false
---

# BooleanColumn

Displays true/false values as colored icons with optional text labels.

```php
use NyonCode\WireTable\Columns\BooleanColumn;
```

## Basic Usage

```php
BooleanColumn::make('is_active')
BooleanColumn::make('email_verified_at')   // null = false, non-null = true
```

## Custom Icons & Colors

```php
BooleanColumn::make('is_verified')
    ->trueIcon('check-circle')
    ->falseIcon('x-circle')
    ->trueColor('success')
    ->falseColor('danger')
```

## With Labels

```php
BooleanColumn::make('is_published')
    ->labels('Published', 'Draft')
```

## BooleanColumn API

```php
->trueIcon(string|Icon $icon)        // default: 'check-circle'
->falseIcon(string|Icon $icon)       // default: 'x-circle'
->trueColor(string|Color $color)     // default: 'success'
->falseColor(string|Color $color)    // default: 'danger'
->labels(?string $trueLabel, ?string $falseLabel)  // text beside the icon
```
