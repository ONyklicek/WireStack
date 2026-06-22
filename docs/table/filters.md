---
order: 30
---

# Filters

Wire Table provides 4 built-in filter types plus the ability to build custom filters. Filters live in the filter bar above the table and persist in Livewire state via `$tableFilters`.

---

## Table of Contents

1. [Filter Flow](#filter-flow)
2. [Shared Filter API](#shared-filter-api)
3. [Filter Indicators](#filter-indicators)
4. [SelectFilter](#selectfilter)
5. [DateFilter](#datefilter)
6. [NumberRangeFilter](#numberrangefilter)
7. [TernaryFilter](#ternaryfilter)
8. [Filtering by Sub-Row Values](#filtering-by-sub-row-values)
9. [Column-Level Filters](#column-level-filters)
10. [Custom Filter Class](#custom-filter-class)
11. [Patterns & Recipes](#patterns--recipes)

---

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

- **Per filter type** — override `render()` in a custom `Filter` subclass and point it at your own Blade view (see [Custom Filter Class](#custom-filter-class)).
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

---

## SelectFilter

Dropdown filter for predefined options. The most common filter type.

```php
use NyonCode\WireTable\Filters\SelectFilter;
```

### Basic Usage

```php
SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'banned' => 'Banned',
    ])
```

### With Placeholder

The first item is always an empty "All" option. To customize:

```php
SelectFilter::make('role')
    ->options([
        '' => 'All Roles',           // explicit placeholder
        'admin' => 'Admin',
        'editor' => 'Editor',
        'viewer' => 'Viewer',
    ])
```

### Multiple Selection

```php
SelectFilter::make('tags')
    ->options(Tag::pluck('name', 'id')->toArray())
    ->multiple()
    ->label('Tags')
```

When `multiple()`, applies `whereIn()` instead of `where()`.

### Searchable Dropdown

```php
SelectFilter::make('country')
    ->options(Country::pluck('name', 'code')->toArray())
    ->searchable()
    ->label('Country')
```

Renders a searchable dropdown (Alpine.js powered) instead of native `<select>`.

### Native HTML Select

```php
SelectFilter::make('type')
    ->options([...])
    ->native()                       // native <select> element (faster render)
```

### From Database

```php
SelectFilter::make('department')
    ->options(fn () => Department::orderBy('name')->pluck('name', 'id')->toArray())
```

Options can be a Closure — evaluated lazily on render.

### Custom Query

```php
SelectFilter::make('has_avatar')
    ->options([
        'yes' => 'With Avatar',
        'no' => 'Without Avatar',
    ])
    ->query(fn (Builder $query, string $value) => match($value) {
        'yes' => $query->whereNotNull('avatar_url'),
        'no' => $query->whereNull('avatar_url'),
    })
```

### SelectFilter API

```php
->options(array|Closure $options)    // ['value' => 'Label', ...]
->multiple(bool $multiple = true)    // multi-select mode
->searchable(bool $searchable = true) // searchable dropdown
->native(bool $native = true)        // native <select>
```

---

## DateFilter

Date filter with single date or date range mode. Renders native date input(s).

```php
use NyonCode\WireTable\Filters\DateFilter;
```

### Single Date

The default mode renders one date input and matches that exact day.

```php
DateFilter::make('created_at')
// Applies: WHERE DATE(created_at) = '2024-01-15'
```

### Date Range

Call `range()` to render two date inputs ("from" and "to"). The user can fill
either side, so open-ended ranges work without extra configuration.

```php
DateFilter::make('created_at')
    ->range()
// Renders two date inputs.
// Both set:  WHERE DATE(created_at) >= from AND <= to
// Only from: WHERE DATE(created_at) >= from
// Only to:   WHERE DATE(created_at) <= to
```

### Custom Labels

In range mode the labels are used as the input placeholders.

```php
DateFilter::make('period')
    ->column('created_at')
    ->range()
    ->fromLabel('Created after')
    ->toLabel('Created before')
```

### Month + Year

Call `month()` to filter by a whole month instead of an exact day. It renders a
native month picker (`<input type="month">`) and matches every record in the
selected month:

```php
DateFilter::make('billed_at')
    ->month()
// Value "2026-06" applies: WHERE YEAR(billed_at) = 2026 AND MONTH(billed_at) = 6
```

Combine with [`subRows()`](#filtering-by-sub-row-values) to filter parents by
the month of their child records.

### Date Constraints

```php
DateFilter::make('birth_date')
    ->minDate('1900-01-01')
    ->maxDate(now()->format('Y-m-d'))
```

### DateFilter API

```php
->range(bool $range = true)         // two inputs (from/to) instead of one
->month(bool $month = true)         // month picker, matches whole month
->fromLabel(string $label)          // "from" placeholder (default: 'From')
->toLabel(string $label)            // "to" placeholder (default: 'To')
->minDate(string $date)             // min selectable date
->maxDate(string $date)             // max selectable date
```

### Range Behavior

| from | to | Condition |
|------|----|-----------|
| set | null | `WHERE DATE(column) >= from` |
| null | set | `WHERE DATE(column) <= to` |
| set | set | `WHERE DATE(column) >= from AND <= to` |
| null | null | No filter applied |

---

## NumberRangeFilter

Numeric range filter with min/max inputs. Renders two number inputs.

```php
use NyonCode\WireTable\Filters\NumberRangeFilter;
```

### Basic Usage

```php
NumberRangeFilter::make('price')
    ->min(0)
    ->max(10000)
    ->step(0.01)
```

### Integer Range

```php
NumberRangeFilter::make('age')
    ->min(18)
    ->max(100)
    ->step(1)
```

### Custom Labels

```php
NumberRangeFilter::make('salary')
    ->min(0)
    ->max(500000)
    ->step(1000)
    ->minLabel('Minimum Salary')
    ->maxLabel('Maximum Salary')
```

### Range Behavior

| min | max | Condition |
|-----|-----|-----------|
| set | null | `WHERE column >= min` |
| null | set | `WHERE column <= max` |
| set | set | `WHERE column >= min AND column <= max` |
| null | null | No filter applied |

### NumberRangeFilter API

```php
->min(float $min)                    // minimum allowed value
->max(float $max)                    // maximum allowed value
->step(float $step)                  // input step increment
->minLabel(string $label)            // label for min input (default: 'Min')
->maxLabel(string $label)            // label for max input (default: 'Max')
```

---

## TernaryFilter

Three-state filter: Yes / No / All. Perfect for boolean columns and "has/doesn't have" relationships.

```php
use NyonCode\WireTable\Filters\TernaryFilter;
```

### Basic Boolean

```php
TernaryFilter::make('is_active')
// Shows: All | Yes | No
// Yes: WHERE is_active = 1
// No: WHERE is_active = 0
```

### Nullable Column

```php
TernaryFilter::make('email_verified_at')
    ->nullable()
// Yes: WHERE email_verified_at IS NOT NULL
// No: WHERE email_verified_at IS NULL
```

### Custom Labels

```php
TernaryFilter::make('verified')
    ->label('Verification Status')
    ->trueLabel('Verified Only')
    ->falseLabel('Unverified Only')
```

### Custom Query Logic

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

### TernaryFilter API

```php
->trueLabel(string $label)          // default: 'Yes'
->falseLabel(string $label)         // default: 'No'
->allLabel(string $label)           // placeholder for the "no filter" option
->nullable(bool $nullable = true)   // "false" also matches IS NULL
->query(Closure $fn)                // custom query: fn(Builder $q, $value)
```

### State Values

| UI State | Submitted Value | Default Behavior |
|----------|-----------------|-----------------|
| All | `null` | No filter |
| Yes | `'1'` | `WHERE column = 1` |
| No | `'0'` | `WHERE column = 0` (or `= 0 OR IS NULL` if nullable) |

---

## Filtering by Relationships

Use `->query()` with `whereHas()` to filter by related model attributes:

```php
// BelongsTo — filter by related model
SelectFilter::make('category')
    ->options(Category::orderBy('name')->pluck('name', 'id')->toArray())
    ->query(fn (Builder $query, string $value) =>
        $query->whereHas('category', fn ($q) => $q->where('id', $value))
    )

// BelongsToMany
SelectFilter::make('tags')
    ->options(Tag::orderBy('name')->pluck('name', 'id')->toArray())
    ->multiple()
    ->query(fn (Builder $query, array $values) =>
        $query->whereHas('tags', fn ($q) => $q->whereIn('id', $values))
    )

// HasMany (existence) — use TernaryFilter
TernaryFilter::make('has_comments')
    ->label('Has Comments')
    ->query(fn (Builder $q, $value) => $value === '1'
        ? $q->has('comments')
        : $q->doesntHave('comments'))
```

---

## Filtering by Sub-Row Values

When the table has [sub-rows](sub-rows.md), mark a filter with `subRows()` to
target the **child** records instead of parent columns. One call constrains all
three places at once:

- **parents** — reduced to those having at least one matching child (`whereHas`),
- **displayed sub-rows** — only matching children render in the expanded panel,
- **rollup aggregates** — `->sums()` / `->counts()` cells (over the same
  relation as `subRows()`) and their footer grand totals count only the
  matching children,
- **sub-row grand totals** — `query`-scoped summaries on sub-row columns total
  only the matching children in the main footer.

See [Sub-Rows — Building the Diagram](sub-rows.md#building-the-diagram) for a
complete worked example of filters + rollups + totals.

```php
$table
    ->subRows('items')
    ->filters([
        // "Month/Year" — show only invoices with items billed that month,
        // and only those items inside each expanded invoice
        DateFilter::make('billed_at')->month()->subRows(),

        // Any filter type works — this matches a child column by equality
        SelectFilter::make('status')
            ->options(['open' => 'Open', 'closed' => 'Closed'])
            ->subRows(),
    ])
```

The filter's column names refer to the **child** model. A `->query()` callback
on a sub-row scoped filter receives the child query builder. When the table has
no sub-row relation configured, `subRows()` is ignored and the filter behaves
like a regular parent filter.

With multiple sub-row scoped filters active, a parent survives only when at
least one child matches **all** of them combined, so every surviving parent has
children to display.

---

## Column-Level Filters

In addition to dedicated filter components, any column can have an inline filter directly in its header cell. See [Columns — Column-Level Filtering](columns.md#column-level-filtering).

```php
TextColumn::make('status')
    ->filterable()
    ->filterAsSelect(['active' => 'Active', 'inactive' => 'Inactive'])

TextColumn::make('price')
    ->filterable()
    ->filterAsNumberRange(0, 10000)

TextColumn::make('created_at')
    ->filterable()
    ->filterAsDateRange()
```

Column filters use the `$columnFilters` Livewire property (separate from `$tableFilters`).

---

## Custom Filter Class

For reusable, complex filters, extend the base `Filter` class.

### Skeleton

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class MyFilter extends Filter
{
    // Custom properties
    protected string $myOption = 'default';

    // Fluent setter
    public function myOption(string $value): static
    {
        $this->myOption = $value;
        return $this;
    }

    // Getter (for Blade view)
    public function getMyOption(): string
    {
        return $this->myOption;
    }

    // Override apply logic
    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value)) {
            return $query;
        }

        // Your custom query logic
        return $query->where(...);
    }

    // Custom Blade view (optional) — override render() and point at your view.
    // The view receives 'filter' (this instance) and 'value' (current state).
    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view('filters.my-filter', ['filter' => $this, 'value' => $value])->render();
    }
}
```

### Example: JSON Contains Filter

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

// Extends SelectFilter so it inherits ->options()/->searchable()/->native().
class JsonContainsFilter extends SelectFilter
{
    protected string $jsonPath = '';

    public function jsonPath(string $path): static
    {
        $this->jsonPath = $path;
        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value)) {
            return $query;
        }

        $column = $this->getColumn();

        if ($this->jsonPath) {
            return $query->whereJsonContains("{$column}->{$this->jsonPath}", $value);
        }

        return $query->whereJsonContains($column, $value);
    }
}
```

Usage:
```php
JsonContainsFilter::make('permissions')
    ->column('settings')
    ->jsonPath('permissions')
    ->options(['admin' => 'Admin', 'edit' => 'Edit', 'view' => 'View'])
```

### Example: Geo Radius Filter

```php
namespace App\Wire\Filters;

use NyonCode\WireTable\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class GeoRadiusFilter extends Filter
{
    protected float $defaultRadius = 10.0;
    protected string $latColumn = 'latitude';
    protected string $lngColumn = 'longitude';

    public function radius(float $km): static
    {
        $this->defaultRadius = $km;
        return $this;
    }

    public function coordinates(string $lat, string $lng): static
    {
        $this->latColumn = $lat;
        $this->lngColumn = $lng;
        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        if (empty($value['lat']) || empty($value['lng'])) {
            return $query;
        }

        $lat = (float) $value['lat'];
        $lng = (float) $value['lng'];
        $radius = (float) ($value['radius'] ?? $this->defaultRadius);

        // Haversine formula (returns km)
        $haversine = "(6371 * acos(
            cos(radians(?)) * cos(radians({$this->latColumn})) *
            cos(radians({$this->lngColumn}) - radians(?)) +
            sin(radians(?)) * sin(radians({$this->latColumn}))
        ))";

        return $query
            ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radius]);
    }

    public function getDefaultRadius(): float
    {
        return $this->defaultRadius;
    }

    public function render(mixed $value = null): string
    {
        if (! $this->canView()) {
            return '';
        }

        return view('filters.geo-radius', ['filter' => $this, 'value' => $value])->render();
    }
}
```

## Patterns & Recipes

### E-commerce Product Filters

```php
$table->filters([
    SelectFilter::make('category')
        ->options(Category::orderBy('name')->pluck('name', 'id')->toArray())
        ->searchable()
        ->query(fn (Builder $q, string $v) => $q->whereHas('category', fn ($q) => $q->where('id', $v))),

    SelectFilter::make('brand')
        ->options(Brand::orderBy('name')->pluck('name', 'id')->toArray())
        ->searchable()
        ->multiple(),

    NumberRangeFilter::make('price')
        ->min(0)
        ->max(100000)
        ->step(100)
        ->minLabel('Min Price')
        ->maxLabel('Max Price'),

    TernaryFilter::make('in_stock')
        ->label('Availability')
        ->trueLabel('In Stock')
        ->falseLabel('Out of Stock')
        ->query(fn (Builder $q, $value) => $value === '1'
            ? $q->where('stock_quantity', '>', 0)
            : $q->where('stock_quantity', '<=', 0)),

    SelectFilter::make('rating')
        ->options([
            '5' => '★★★★★',
            '4' => '★★★★☆ and up',
            '3' => '★★★☆☆ and up',
        ])
        ->query(fn (Builder $q, string $v) => $q->where('avg_rating', '>=', (int)$v)),

    TernaryFilter::make('has_discount')
        ->label('Discounted')
        ->query(fn (Builder $q, $value) => $value === '1'
            ? $q->whereNotNull('discount_percent')
            : $q->whereNull('discount_percent')),
]);
```

### Admin User Filters

```php
$table->filters([
    SelectFilter::make('role')
        ->options(Role::pluck('display_name', 'name')->toArray())
        ->multiple(),

    TernaryFilter::make('email_verified_at')
        ->label('Email Verified')
        ->nullable(),

    DateFilter::make('created_at')
        ->range()
        ->fromLabel('Registered after')
        ->toLabel('Registered before'),

    TernaryFilter::make('two_factor_enabled')
        ->label('2FA Enabled'),

    SelectFilter::make('last_activity')
        ->label('Activity')
        ->options([
            'today' => 'Active today',
            'week' => 'Active this week',
            'month' => 'Active this month',
            'inactive' => 'Inactive (30+ days)',
        ])
        ->query(fn (Builder $q, string $v) => match($v) {
            'today' => $q->whereDate('last_active_at', today()),
            'week' => $q->where('last_active_at', '>=', now()->subWeek()),
            'month' => $q->where('last_active_at', '>=', now()->subMonth()),
            'inactive' => $q->where('last_active_at', '<', now()->subMonth()),
        }),
]);
```

### Order Management Filters

```php
$table->filters([
    SelectFilter::make('status')
        ->options([
            'pending' => 'Pending',
            'processing' => 'Processing',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
            'refunded' => 'Refunded',
        ])
        ->multiple()
        ->default(['pending', 'processing']),   // pre-select active orders

    DateFilter::make('ordered_at')
        ->range(),

    NumberRangeFilter::make('total')
        ->min(0)
        ->max(1000000)
        ->step(100)
        ->minLabel('Min Total')
        ->maxLabel('Max Total'),

    SelectFilter::make('payment_method')
        ->options([
            'card' => 'Credit Card',
            'bank' => 'Bank Transfer',
            'cash' => 'Cash on Delivery',
        ]),

    TernaryFilter::make('has_notes')
        ->label('Has Notes')
        ->query(fn (Builder $q, $value) => $value === '1'
            ? $q->whereNotNull('notes')->where('notes', '!=', '')
            : $q->where(fn ($q2) => $q2->whereNull('notes')->orWhere('notes', ''))),
]);
```

### Filter with Dependent Options

```php
// Country → City cascade (requires Livewire re-render)
SelectFilter::make('country')
    ->options(Country::pluck('name', 'id')->toArray())
    ->searchable(),

SelectFilter::make('city')
    ->options(function () {
        $countryId = $this->tableFilters['country'] ?? null;
        if (! $countryId) {
            return [];
        }
        return City::where('country_id', $countryId)->pluck('name', 'id')->toArray();
    })
    ->searchable()
    ->visible(fn () => ! empty($this->tableFilters['country'])),
```
