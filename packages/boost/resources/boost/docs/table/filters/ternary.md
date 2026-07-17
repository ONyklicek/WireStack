---
order: 31
nav: false
---

# TernaryFilter

Three-state filter: Yes / No / All. Perfect for boolean columns and "has/doesn't have" relationships.

Renders through the same combobox as [`SelectFilter`](select.md) and the forms
`Select` field, so an open boolean filter looks identical to any other select.
"All" is the placeholder — picking it clears the filter. Opt into a browser-native
`<select>` with [`->native()`](#native-html-select).

```php
use NyonCode\WireTable\Filters\TernaryFilter;
```

## Basic Boolean

```php
TernaryFilter::make('is_active')
// Shows: All | Yes | No
// Yes: WHERE is_active = 1
// No: WHERE is_active = 0
```

## Nullable Column

```php
TernaryFilter::make('email_verified_at')
    ->nullable()
// Yes: WHERE email_verified_at IS NOT NULL
// No: WHERE email_verified_at IS NULL
```

## Custom Labels

```php
TernaryFilter::make('verified')
    ->label('Verification Status')
    ->trueLabel('Verified Only')
    ->falseLabel('Unverified Only')
```

## Custom Query Logic

Use a single `query()` callback; it receives the builder and the selected value
(`'1'` for the "true" option, `'0'` for the "false" option).

```php
TernaryFilter::make('has_orders')
    ->label('Has Orders')
    ->query(fn (Builder $query, $value) => $value === '1'
        ? $query->has('orders')
        : $query->doesntHave('orders'))
```

```php
TernaryFilter::make('overdue')
    ->label('Overdue')
    ->query(fn (Builder $query, $value) => $value === '1'
        ? $query->where('due_at', '<', now())
        : $query->where('due_at', '>=', now()))
```

## Native HTML Select

```php
TernaryFilter::make('is_active')
    ->native()                      // browser-native <select> element (faster render)
```

Opts out of the shared combobox, so the filter no longer matches the other
selects. Prefer it only where render cost matters more than a consistent look.

## TernaryFilter API

```php
->trueLabel(string $label)          // default: 'Yes'
->falseLabel(string $label)         // default: 'No'
->allLabel(string $label)           // placeholder for the "no filter" option
->nullable(bool $nullable = true)   // "false" also matches IS NULL
->native(bool $native = true)       // opt into a browser-native <select> (default: false)
->query(Closure $fn)                // custom query: fn(Builder $q, $value)
```

## State Values

| UI State | Submitted Value | Default Behavior |
|----------|-----------------|-----------------|
| All | `null` | No filter |
| Yes | `'1'` | `WHERE column = 1` |
| No | `'0'` | `WHERE column = 0` (or `= 0 OR IS NULL` if nullable) |
