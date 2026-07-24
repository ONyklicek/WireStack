---
order: 30
---

# Filters

Wire Table provides **5 built-in filter types** plus the ability to build custom
filters. Filters live in the filter bar above the table and persist in Livewire
state via `$tableFilters`. This page covers the flow and the shared API; each
type has its own page.

## Filter Types

| Filter | Use for |
|--------|---------|
| [TextFilter](text.md) | Free-text match with an operator (`like`, `starts_with`, …) |
| [SelectFilter](select.md) | Single/multi choice from options, relations, or enums |
| [DateFilter](date.md) | Single date, date range, or month + year |
| [NumberRangeFilter](number-range.md) | Min/max numeric range |
| [TernaryFilter](ternary.md) | Three-state boolean (all / true / false) |

## More

- [Relationship & Sub-Row Filters](relationships.md) — filter by related models and sub-row values
- [Column-Level Filters](column-level.md) — inline filter inputs in a column header
- [Custom Filter Class](custom.md) — build your own filter component
- [Patterns & Recipes](patterns.md) — full example filter bars

## Filter Flow

```
Table::filters([...])
│
├── Render: Filter components in sidebar/header bar
│   └── Each filter renders its own Blade view
│
├── State: $tableFilters array ['name' => 'value', ...]
│   └── Persisted in Livewire component state
│
└── Apply: When state changes
    ├── Each filter's apply() or query() callback is called
    ├── Conditions added to Eloquent Builder
    └── Table re-queries with filters applied
```

Filter application flows through the Core `ApplyFilters` pipe in the QueryExecutor pipeline.

---

## Shared Filter API

Every filter inherits from the base `Filter` class.

### Factory & Identity

```php
Filter::make(string $name)           // static factory
->label(?string $label)               // display label (auto-generated from name)
->getName(): string
->getLabel(): string
```

### Column Binding

```php
->column(string $column)             // DB column to filter on (defaults to $name)
->getColumn(): string
```

When `column()` is not called, the filter uses its `$name` as the database column.

### Custom Query Logic

```php
->query(Closure $fn)                 // custom query callback
```

The callback signature is `function (Builder $query, mixed $value): Builder` — it **must return the query builder** (the runtime reassigns `$query` to the callback's return value).

```php
SelectFilter::make('activity_level')
    ->options([...])
    ->query(fn (Builder $query, string $value) => match ($value) {
        'active' => $query->where('last_active_at', '>=', now()->subDays(7)),
        'inactive' => $query->where('last_active_at', '<', now()->subDays(30)),
        'new' => $query->where('created_at', '>=', now()->subDays(7)),
        default => $query,
    })
```

#### What `$value` Contains

The shape depends on the filter type — a range filter hands you an array, a
ternary hands you a real boolean:

| Filter | `$value` | Example |
|--------|----------|---------|
| `TextFilter` | `string` — what the user typed | `'invoice'` |
| `SelectFilter` | `string\|int` option key | `'active'` |
| `SelectFilter` + `->multiple()` | `array` of option keys | `['active', 'pending']` |
| `TernaryFilter` | `bool` — `true` for Yes, `false` for No | `false` |
| `NumberRangeFilter` | `array{min, max}` — either side may be `''` | `['min' => '10', 'max' => '']` |
| `DateFilter` | `string` date | `'2026-07-23'` |
| `DateFilter` + `->range()` | `array{from, to}` | `['from' => '2026-01-01', 'to' => '']` |
| `DateFilter` + `->month()` | `string` `'YYYY-MM'` | `'2026-07'` |

The callback is only called while the filter is **active** — an empty state
(`null`, `''`, `[]`, or "All" on a ternary) clears the filter and never reaches
your closure, so you do not need to guard against it. The multi-field filters
are the exception in one direction: a range stays active while *either* side is
filled, so check each bound before using it.

A third argument carries the raw submitted state before normalization, for the
rare callback that needs the transport form:

```php
->query(function (Builder $query, mixed $value, mixed $raw) { … })
```

### Visibility & Permissions

```php
->hidden(bool|Closure $hidden = true)
->visible(bool|Closure $visible = true)
->isHidden(): bool
->permission(string $permission)     // visible only if user has permission
```

```php
DateFilter::make('deleted_at')
    ->range()
    ->permission('view-deleted-records')

SelectFilter::make('internal_status')
    ->options([...])
    ->visible(fn () => auth()->user()->is_admin)
```

### Default Value

```php
->default(mixed $value)              // pre-selected on initial load
->getDefault(): mixed
```

```php
SelectFilter::make('status')
    ->options([...])
    ->default('active')              // "active" pre-selected
```

### Multiple Selection

```php
->multiple(bool $multiple = true)
```

When enabled, the filter accepts an array of values and applies `whereIn()`.

### View Customization

There is no fluent `->view()` setter. Customize a filter's UI in one of two ways:

- **Per filter type** — override `render()` in a custom `Filter` subclass and point it at your own Blade view (see [Custom Filter Class](custom.md)).
- **Project-wide** — publish the package views and edit the partials under `resources/views/vendor/wire-table/tables/filters/` (`select`, `date`, `number-range`, `ternary`, `form-field`).

```bash
php artisan vendor:publish --tag=wire-table::views
```

---

## Filter Indicators

Active filters render as removable chips under the table toolbar — each chip
shows a human-readable label and a × button that clears just that filter.
With more than one active filter, a "Reset filters" link appears next to
the chips.

Default labels are generated per filter type:

| Filter | Example chip |
|---|---|
| `SelectFilter` | `Status: Active` (option label, not raw value) |
| `SelectFilter` + `multiple()` | `Status: Active, Trial` |
| `TernaryFilter` | `Verified: Yes` (true/false labels) |
| `NumberRangeFilter` | `Price: 10 – 100`, `Price: ≥ 10`, `Price: ≤ 100` |
| `DateFilter` | `Created: 2026-06-11` |
| `DateFilter` + `range()` | `Created: 2026-06-01 – 2026-06-30` |
| `DateFilter` + `month()` | `Billed: May 2026` (translated month name) |
| base `Filter` | `Label: value` |

### Customizing the Chip

```php
SelectFilter::make('status')
    ->options([...])
    ->indicator('Only active customers')              // fixed label

DateFilter::make('created_at')
    ->indicator(fn ($value) => 'Since '.$value)       // closure: fn ($value, Filter $filter)
```

Returning `null` or an empty string from the closure hides the chip while
the filter stays applied. Hidden/unauthorized filters never produce chips.

### Component API

```php
$component->getActiveFilterIndicators();   // ['status' => 'Status: Active', ...]
$component->removeTableFilter('status');   // clear one filter (chip × button)
$component->resetTableFilters();           // clear all filters + search
```

[Column-level header filters](column-level.md) produce the same chips alongside
panel filters (they are canonical `Filter` objects too), with their own handlers:

```php
$component->getActiveColumnFilterIndicators();  // ['name' => 'Name: Widget', ...]
$component->removeColumnFilter('name');         // clear one column filter
```

## Mobile

The filter bar and column-toggle menu open as a bottom sheet on a phone.
Configure globally via the `wire-core.mobile` block, or per component with
`->sheetOnMobile()` / `->mobileBreakpoint()` — see
[mobile presentation](../../configuration.md#mobile).
