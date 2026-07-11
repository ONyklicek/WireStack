---
order: 31
nav: false
---

# TextFilter

Free-text filter with a configurable SQL operator. Substring `LIKE` by default,
so it works as a scoped search box for a single column, plus prefix / suffix /
exact / comparison operators. It is also the engine behind a column header's
`->filterable()` text filter.

```php
use NyonCode\WireTable\Filters\TextFilter;
```

## Basic (substring match)

```php
TextFilter::make('name')
// WHERE name LIKE '%value%'
```

## Operators

```php
TextFilter::make('sku')
    ->operator('starts_with')   // WHERE sku LIKE 'value%'

TextFilter::make('email')
    ->operator('=')             // exact match

TextFilter::make('age')
    ->operator('>=')            // numeric / lexical comparison
```

Supported: `like` (default), `starts_with`, `ends_with`, `equals` / `=`, `!=`,
`>`, `>=`, `<`, `<=`.

## Custom Query Logic

```php
TextFilter::make('title')
    ->query(fn (Builder $query, $value) => $query->where('title', 'like', "%{$value}%"))
```

## TextFilter API

```php
->operator(string $operator)        // default: 'like'
->debounce(?int $ms)                // debounce for the live input
->query(Closure $fn)                // custom query: fn(Builder $q, $value)
```

The comparison flows through the canonical `QueryPlanner` (the operator maps to a
`LIKE` / comparison clause), so joins and column qualification are handled
identically to every other filter.
