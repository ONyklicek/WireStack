# Filters

WireTable has two independent filtering systems:

1. **Table Filters** (this page) - standalone filter components defined in `$table->filters([...])`, displayed in a dedicated filter area above or beside the table.
2. **Column Filters** - inline filter inputs rendered directly in each column header. See [Column Filters](columns.md#column-filters) in the Columns documentation.

Both systems can be used simultaneously. Table filters are stored in `$tableFilters`, column filters in `$columnFilters`.

---

## Table Filters

All table filters share a common base API.

## Base Filter

```php
use NyonCode\WireTable\Filters\Filter;

Filter::make('status')
    ->label('Status')
    ->column('status')                 // Database column (defaults to filter name)
    ->default('active')                // Default value
    ->placeholder('Select...')         // Placeholder text
    ->hidden()                         // Hide filter
    ->hidden(fn () => ! auth()->user()->isAdmin())  // Conditional
    ->permission('view-filters')       // Require permission
```

### Custom Query

```php
Filter::make('active_users')
    ->query(fn (Builder $query, mixed $value) =>
        $query->where('status', $value)
            ->where('last_login_at', '>', now()->subDays(30))
    )
```

### Relationship Filters

Dot notation is automatically resolved with `whereHas`:

```php
Filter::make('user.role')             // Filters through user relationship
Filter::make('category.type')         // Filters through category relationship
```

---

## SelectFilter

Dropdown filter with predefined options.

```php
use NyonCode\WireTable\Filters\SelectFilter;

SelectFilter::make('status')
    ->options([
        'active' => 'Active',
        'inactive' => 'Inactive',
        'pending' => 'Pending',
    ])
    ->placeholder('All statuses')
    ->searchable()                     // Enable search in dropdown
    ->native(false)                    // Custom styled dropdown

// Dynamic options
SelectFilter::make('category')
    ->options(fn () => Category::pluck('name', 'id')->toArray())

// Multiple selection
SelectFilter::make('tags')
    ->options([...])
    ->multiple()
```

---

## DateFilter

Date and date range filtering.

```php
use NyonCode\WireTable\Filters\DateFilter;

// Single date
DateFilter::make('created_at')
    ->label('Created')
    ->minDate('2024-01-01')
    ->maxDate(now()->format('Y-m-d'))

// Date range
DateFilter::make('created_at')
    ->range()
    ->fromLabel('From')                // Default: 'Od'
    ->toLabel('To')                    // Default: 'Do'
    ->minDate('2024-01-01')
    ->maxDate(now()->format('Y-m-d'))
```

The date range filter expects an array value `['from' => '...', 'to' => '...']` and applies `whereDate` queries.

---

## NumberRangeFilter

Numeric range with min/max inputs.

```php
use NyonCode\WireTable\Filters\NumberRangeFilter;

NumberRangeFilter::make('price')
    ->label('Price Range')
    ->min(0)
    ->max(10000)
    ->step(100)
    ->minLabel('Min price')           // Input label
    ->maxLabel('Max price')           // Input label
```

Applies `where('price', '>=', $min)` and `where('price', '<=', $max)`.

---

## TernaryFilter

Three-state filter: Yes / No / All.

```php
use NyonCode\WireTable\Filters\TernaryFilter;

TernaryFilter::make('is_active')
    ->trueLabel('Active')              // Default: 'Ano'
    ->falseLabel('Inactive')           // Default: 'Ne'
    ->allLabel('All')                  // Default: 'Vše'

// Nullable mode - false matches both false AND null
TernaryFilter::make('verified_at')
    ->nullable()
    ->trueLabel('Verified')
    ->falseLabel('Not verified')
```

---

## Combining Filters

```php
$table->filters([
    SelectFilter::make('status')
        ->options([
            'active' => 'Active',
            'inactive' => 'Inactive',
        ]),

    DateFilter::make('created_at')
        ->range(),

    NumberRangeFilter::make('salary')
        ->min(0)
        ->max(200000)
        ->step(1000),

    TernaryFilter::make('is_verified')
        ->trueLabel('Verified')
        ->falseLabel('Unverified'),

    SelectFilter::make('department.name')
        ->options(fn () => Department::pluck('name', 'name')->toArray())
        ->searchable(),
])
```
