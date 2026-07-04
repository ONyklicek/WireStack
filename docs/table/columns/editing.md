---
order: 28
nav: false
---

# Editing & Column-Level Filters

## Column-Level Filtering

Beyond the dedicated Filter classes, any column can have an inline filter in its header.

```php
// Select filter in column header
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

// Boolean filter
BooleanColumn::make('is_active')
    ->filterable()
    ->filterAsBoolean()

// Date range filter
TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()

// Number range filter
TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)        // min, max, optional step

// Custom filter logic
TextColumn::make('name')
    ->filterable()
    ->filterUsing(fn (Builder $query, mixed $value) => $query->where('name', 'like', "%{$value}%"))
    ->filterDebounce(500)

// Filter with operator
TextColumn::make('age')
    ->filterable()
    ->filterOperator('>=')
```

### Column-Level Filter API

```php
->filterable(bool $filterable = true, string $type = 'text', array|string $options = [])
->isFilterable(): bool
->filterAsSelect(array|string $options, ?string $placeholder = null)  // array or enum class
->filterAsDate(?string $minDate = null, ?string $maxDate = null)
->filterAsDateRange(?string $minDate = null, ?string $maxDate = null)
->filterAsNumberRange(?float $min = null, ?float $max = null, ?float $step = null)
->filterAsBoolean(?string $trueLabel = null, ?string $falseLabel = null)
->filterOperator(string $operator)     // '=', '!=', '>', '<', '>=', '<=', 'like' (default, partial match), 'starts_with', 'ends_with'
->filterDebounce(int $ms)
->filterPlaceholder(?string $placeholder)
->filterUsing(Closure $fn)             // fn(Builder $query, mixed $value)
```

---

## Inline Editing

Columns can also use the generic `editable()` API (in addition to dedicated TextInputColumn/SelectColumn/ToggleColumn):

```php
TextColumn::make('name')
    ->editable()                              // type defaults to 'text'
    ->editableRules(fn ($record) => ['required', 'max:255'])
    ->editableUsing(function ($record, $column, $value) {
        $record->update([$column => $value]);
    })

TextColumn::make('category')
    // editable(enabled, type, options) — 'text' | 'select' | 'toggle'
    ->editable(true, 'select', ['a' => 'Category A', 'b' => 'Category B'])
    ->editableRules(fn ($record) => ['required', 'in:a,b'])
```

The `options` argument of both `editable(type: 'select', …)` and `filterable()` /
`filterAsSelect()` accepts a PHP enum class as well — it expands to `value => label` exactly
like the dedicated `SelectColumn`/`SelectFilter`. See [Enum Options](#enum-options).
