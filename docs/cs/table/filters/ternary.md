---
order: 31
nav: false
---

# TernaryFilter

Trojstavový filtr: Yes / No / All. Ideální pro boolean sloupce a relace „má/nemá".

```php
use NyonCode\WireTable\Filters\TernaryFilter;
```

## Základní boolean

```php
TernaryFilter::make('is_active')
// Ukazuje: All | Yes | No
// Yes: WHERE is_active = 1
// No: WHERE is_active = 0
```

## Nullable sloupec

```php
TernaryFilter::make('email_verified_at')
    ->nullable()
// Yes: WHERE email_verified_at IS NOT NULL
// No: WHERE email_verified_at IS NULL
```

## Vlastní popisky

```php
TernaryFilter::make('verified')
    ->label('Verification Status')
    ->trueLabel('Verified Only')
    ->falseLabel('Unverified Only')
```

## Vlastní logika dotazu

Použijte jediný callback `query()`; dostane builder a vybranou hodnotu
(`'1'` pro option „true", `'0'` pro option „false").

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

## API TernaryFilter

```php
->trueLabel(string $label)          // výchozí: 'Yes'
->falseLabel(string $label)         // výchozí: 'No'
->allLabel(string $label)           // placeholder pro option „bez filtru"
->nullable(bool $nullable = true)   // „false" také odpovídá IS NULL
->query(Closure $fn)                // vlastní dotaz: fn(Builder $q, $value)
```

## Hodnoty stavu

| Stav UI | Odeslaná hodnota | Výchozí chování |
|----------|-----------------|-----------------|
| All | `null` | Žádný filtr |
| Yes | `'1'` | `WHERE column = 1` |
| No | `'0'` | `WHERE column = 0` (nebo `= 0 OR IS NULL` pokud nullable) |
