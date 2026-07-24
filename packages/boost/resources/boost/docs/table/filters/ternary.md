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

Use a single `query()` callback; it receives the builder and the picked state as
a **real boolean** — `true` for the "Yes" option, `false` for "No". Picking
"All" clears the filter, so the callback never runs with an empty state.

```php
TernaryFilter::make('has_orders')
    ->label('Has Orders')
    ->query(fn (Builder $query, bool $value) => $value
        ? $query->has('orders')
        : $query->doesntHave('orders'))
```

```php
TernaryFilter::make('overdue')
    ->label('Overdue')
    ->query(fn (Builder $query, bool $value) => $value
        ? $query->where('due_at', '<', now())
        : $query->where('due_at', '>=', now()))
```

Filtering on a relation is the usual reason to reach for `query()`, since
`where(column, bool)` cannot express it:

```php
TernaryFilter::make('invoiced')
    ->label('Invoiced')
    ->query(fn (Builder $query, bool $value) => $value
        ? $query->whereHas('invoice')
        : $query->whereDoesntHave('invoice'))
```

`nullable()` expands the **default** query's "No" branch. A `query()` callback
owns its own query, so it is not applied there — the callback is told which side
was picked and decides how `NULL` should behave.

> **Changed in 1.13.0** — the callback used to receive the raw select state
> (`'true'` / `'false'`), so `$value ? … : …` branched on a truthy string and
> both options returned the same rows. It now receives a real `bool`. A callback
> comparing against a string (`$value === '1'`, `$value === 'true'`) must be
> updated; the raw state is still available as an optional third argument.

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
->query(Closure $fn)                // custom query: fn(Builder $q, bool $value)
```

## State Values

The select submits the option key; `query()` callbacks and the default query
both work with the normalized boolean, so you never branch on the transport
form.

| UI State | Submitted State | `$value` in `query()` | Default Behavior |
|----------|-----------------|-----------------------|------------------|
| All | `''` / `null` | *(not called)* | No filter |
| Yes | `'true'` | `true` | `WHERE column = 1` |
| No | `'false'` | `false` | `WHERE column = 0` (or `= 0 OR IS NULL` if nullable) |

State seeded from a URL or set programmatically may also arrive as `'1'`/`'0'`,
`1`/`0` or a real bool — all are accepted and normalized the same way.
