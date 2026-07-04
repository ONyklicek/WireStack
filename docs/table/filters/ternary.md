---
order: 31
nav: false
---

# TernaryFilter

Three-state filter: Yes / No / All. Perfect for boolean columns and "has/doesn't have" relationships.

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

## TernaryFilter API

```php
->trueLabel(string $label)          // default: 'Yes'
->falseLabel(string $label)         // default: 'No'
->allLabel(string $label)           // placeholder for the "no filter" option
->nullable(bool $nullable = true)   // "false" also matches IS NULL
->query(Closure $fn)                // custom query: fn(Builder $q, $value)
```

## State Values

| UI State | Submitted Value | Default Behavior |
|----------|-----------------|-----------------|
| All | `null` | No filter |
| Yes | `'1'` | `WHERE column = 1` |
| No | `'0'` | `WHERE column = 0` (or `= 0 OR IS NULL` if nullable) |
