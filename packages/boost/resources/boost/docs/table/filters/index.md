---
order: 30
summary: Every built-in filter, the operators they share, the filter panel's layouts and Apply button, and where filter state lives between requests.
---

# Filters

Wire Table provides **7 built-in filter types** plus the ability to build custom
filters. Filters live in the filter panel — behind the toolbar's *Filters*
button, or laid out above the rows — and persist in Livewire state via
`$tableFilters`. This page covers the flow, the operators every filter shares,
the panel, and the shared API; each type has its own page.

## Filter Types

| Filter | Use for |
|--------|---------|
| [TextFilter](text.md) | Free text — contains, is, starts with, their negatives, is empty |
| [SelectFilter](select.md) | Single/multi choice from options, enums or the column's own values, with counts |
| [DateFilter](date.md) | Single date (before / after / on), a range with presets, or month + year |
| [NumberRangeFilter](number-range.md) | Min/max numeric range |
| [TernaryFilter](ternary.md) | Three-state boolean (all / true / false) |
| [TrashedFilter](trashed.md) | Soft deletes: live / deleted / all records |
| [QueryBuilderFilter](query-builder.md) | Conditions the user combines with AND / OR, one level of groups |

## More

- [Relationship & Sub-Row Filters](relationships.md) — filter by related models and sub-row values
- [Column-Level Filters](column-level.md) — a filter row or a funnel per column header, counts, presets, "Only this value"
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
│   ├── {value, operator} for a filter with an operator picker
│   └── Persisted in Livewire component state
│
└── Apply: When state changes (or on Apply, with ->deferFilters())
    ├── Each filter's apply() or query() callback is called
    ├── Conditions added to Eloquent Builder
    └── Table re-queries with filters applied
```

Filter application flows through the Core `ApplyFilters` pipe in the QueryExecutor pipeline.

## Operators

A filter compares one way by default — a text filter *contains*, a select *is*.
`->operators()` lets the user choose instead: a picker appears beside the value,
and the filter's state becomes `{value, operator}`. One vocabulary,
`FilterOperator`, serves every filter, and each case owns its label, whether it
needs a value, and its SQL:

| `FilterOperator` | Value | Label | SQL on a column |
|---|---|---|---|
| `Contains` | `contains` | contains | `LIKE '%v%'` |
| `NotContains` | `not_contains` | does not contain | `IS NULL OR NOT LIKE '%v%'` |
| `Equals` | `equals` | is | `= v` |
| `NotEquals` | `not_equals` | is not | `IS NULL OR != v` |
| `StartsWith` | `starts_with` | starts with | `LIKE 'v%'` |
| `EndsWith` | `ends_with` | ends with | `LIKE '%v'` |
| `In` | `in` | is any of | `IN (…)` |
| `NotIn` | `not_in` | is none of | `IS NULL OR NOT IN (…)` |
| `GreaterThan` / `GreaterThanOrEqual` | `gt` / `gte` | is greater than / is at least | `>` / `>=` |
| `LessThan` / `LessThanOrEqual` | `lt` / `lte` | is less than / is at most | `<` / `<=` |
| `IsEmpty` | `is_empty` | is empty | `IS NULL` (text: `OR = ''`) |
| `IsNotEmpty` | `is_not_empty` | is not empty | `IS NOT NULL` (text: `AND != ''`) |

Which a filter can offer depends on its type — `->operators(true)` offers all of
these, a list offers those, and anything the type cannot apply is dropped:

| Filter | Operators |
|---|---|
| `TextFilter` | contains, does not contain, is, is not, starts with, ends with, is empty, is not empty |
| `SelectFilter` | is any of, is none of, is empty, is not empty |
| `DateFilter` (single date) | is on, is not on, is before, is on or before, is after, is on or after, is empty, is not empty — date wording for the comparisons |
| `NumberRangeFilter`, `TernaryFilter`, `TrashedFilter`, a date range or month | none |

**How the operator in force is chosen:** the one the state names, *if the filter
offers it* — a crafted URL or a stale saved view naming another one is ignored —
then `->defaultOperator()`, then the filter's own comparison, then the first one
offered.

**What the semantics guarantee:**

- **Negatives keep `NULL` rows.** *Does not contain gmail* includes the
  customers with no email; a bare `NOT LIKE` would drop them.
- **Across a relation a negative means "no related record matches"** —
  `whereDoesntHave` with the positive comparison, never `whereHas` with the
  negative one, which would keep a customer with one gmail address and one other.
  *Is empty* across a relation is "has no related record with a value".
- **"Is empty" needs no value.** A filter set to *is empty* or *is not empty* is
  active with the value box empty, and its chip reads `Phone: is empty`.
- **On text, "is empty" also matches `''`**; on numbers and dates it is `IS
  NULL` only (PostgreSQL refuses to compare those columns with a string).
- **One planner clause where possible.** *contains*, *is*, *is any of* and the
  comparisons plan through the `QueryPlanner` like any filter; the negatives and
  the emptiness checks constrain the query through the filter's `apply()`.

```php
use NyonCode\WireTable\Enums\FilterOperator;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;

TextFilter::make('email')
    ->operators(['contains', 'not_contains', 'is_empty']) // [tl! focus:start]
    ->defaultOperator(FilterOperator::NotContains) // [tl! focus:end]

SelectFilter::make('status')
    ->options(['paid' => 'Paid', 'pending' => 'Pending'])
    ->multiple()
    ->operators()                      // is any of, is none of, is empty, is not empty [tl! focus]
```

With `Table::queryString()` the operator travels beside the value under the same
parameter with an `_op` suffix — `?filter_email=gmail&filter_email_op=not_contains`
— and a header filter's as `col_<column>_op`. A column filter takes the same
choice with [`->filterOperators()`](column-level.md#operators-on-a-column).

## The Filter Panel

Three table settings decide how the panel behaves and where it sits:

- **`->deferFilters()`** — the panel's controls stop applying on every change
  and an **Apply** button appears beside *Reset*: one query for "pick three
  things", not three. The controls write to a draft — `tableState.filterDraft`
  instead of `tableState.filters` — so no other request (a page, a sort, a poll,
  a row action) narrows the rows with a half-made choice. *Apply*
  (`applyTableFilters()`) copies the draft into the filters and returns the
  table to page one. Anything else that rewrites the filters — *Reset*, a chip's
  remove, a saved view — starts the draft over from them. Column header filters
  stay live.
- **`->filtersLayout()`** — `FiltersLayout::Dropdown` (default) keeps the panel
  behind the toolbar's *Filters* button, a bottom sheet on a phone.
  `FiltersLayout::AboveContent` lays the filters out above the rows, always
  open — right for a list people narrow constantly.
  `FiltersLayout::AboveContentCollapsible` is the same behind a toggle, closed
  until wanted; open or closed is remembered per browser.
- **`->filtersFormColumns(int)`** — the filters in 1–4 columns from the `sm`
  breakpoint up (always one on a phone); the dropdown widens to fit. A
  [`QueryBuilderFilter`](query-builder.md) always takes a whole row.

Each resolves the per-table call first, then config, then the default:

```php
// config/wire-table.php
'defaults' => [
    'filters_layout' => env('WIRE_TABLE_FILTERS_LAYOUT', 'dropdown'), // 'dropdown' | 'above' | 'above_collapsible'
    'defer_filters' => env('WIRE_TABLE_DEFER_FILTERS', false),
    'facet_cache_ttl' => env('WIRE_TABLE_FACET_CACHE_TTL', 60), // seconds an option count is kept; 0 = every request
],
```

`filtersFormColumns()` has no config key; it defaults to `1`.

```php
<?php

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Enums\FiltersLayout;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Table;

class ListInvoices extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Invoice::class)
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('customer'),
                TextColumn::make('status'),
            ])
            ->filters([
                TextFilter::make('customer')->operators(),
                SelectFilter::make('status')->multiple()
                    ->options(['paid' => 'Paid', 'pending' => 'Pending', 'overdue' => 'Overdue'])
                    ->withCounts(),
                DateFilter::make('issued_at')->presets(),
            ])
            ->filtersLayout(FiltersLayout::AboveContentCollapsible) // [tl! focus:start]
            ->filtersFormColumns(3)
            ->deferFilters(); // [tl! focus:end]
    }

    public function render()
    {
        return view('livewire.list-invoices');
    }
}
```

The panel opens above the rows in three columns; nothing queries until *Apply*.

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

### Operator Picker

```php
->operators(array|bool $operators = true)          // true = every operator the type supports, a list<FilterOperator|string>, false = no picker
->defaultOperator(FilterOperator|string $operator)  // what a fresh filter starts on
->getOperators(): array                             // list<FilterOperator> on the picker — [] without one
->hasOperatorPicker(): bool                         // more than one operator offered
->getDefaultOperator(): FilterOperator              // the default, constrained to what is offered
->getActiveOperator(): FilterOperator               // the operator in force for the current state
->operatorLabel(FilterOperator $operator): string   // the filter's wording — a date filter says "is before"
```

See [Operators](#operators) above for the vocabulary and the semantics.

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

### For Filter Authors

The hooks a [custom filter](custom.md) overrides, and the ones the table calls on
every filter. You rarely call them yourself:

```php
->supportedOperators(): array                      // list<FilterOperator> the type can apply — [] in the base Filter
->operatorFromState(mixed $raw): FilterOperator    // the operator the state names, if offered; else the default
->forState(mixed $raw): static                     // a copy carrying the state's operator — the stored filter is never mutated
->emptyState(): mixed                              // what the filter is cleared back to: [] (multiple), null, or a keyed shape like {from, to}
->stateForCellValue(mixed $value, bool $exclude = false): mixed // the header state "Only / Exclude this value" writes; null = cannot say it
->canExcludeCellValue(): bool                      // whether "Exclude this value" is offered (a negative operator is on the picker)
->builderInput(): string                           // the value control in a query-builder condition: 'text'|'select'|'date'|'boolean'
->spansFullWidth(): bool                           // whether the panel gives the filter a whole grid row
->deferred(bool $deferred = true)                  // set by the table from Table::deferFilters()
->isDeferred(): bool
->wireModelDirective(int $debounce = 0): string    // 'wire:model' when deferred, else 'wire:model.live' (debounced when > 0)
```

A custom filter that supports operators returns them from
`supportedOperators()` and reads `getActiveOperator()` in its `apply()`; the
picker, the `{value, operator}` state, the `_op` URL parameter and the chip
wording come with it.

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
| `DateFilter` + `presets()` | `Created: Last 7 days` (while a preset is picked) |
| any filter with an operator picker | `Email: does not contain gmail`, `Phone: is empty` |
| `QueryBuilderFilter` | `Customer contains Acme and Status is any of Paid` |
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
[mobile presentation](../../start/configuration.md#mobile).
