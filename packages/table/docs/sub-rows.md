# Sub-Rows

Sub-rows display related child records beneath each parent row. They support expand/collapse, filtering, summaries, flatten mode, and custom views.

## Basic Usage

Enable sub-rows by specifying an Eloquent relationship and the columns to display:

```php
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;

public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->columns([
            TextColumn::make('order_number')->sortable(),
            TextColumn::make('customer_name')->searchable(),
            TextColumn::make('total')->money(),
        ])
        ->subRows('items')
        ->subRowColumns([
            TextColumn::make('product_name'),
            TextColumn::make('quantity')->numeric(),
            TextColumn::make('unit_price')->money(),
            TextColumn::make('line_total')->money(),
        ]);
}
```

The `subRows('items')` call expects `items` to be a valid Eloquent relationship method on the `Order` model. The parameter is the **method name** of the relationship, not the table name. WireTable internally calls `$record->items()` to load the child records.

### Model Relationship Example

```php
// app/Models/Order.php
class Order extends Model
{
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}

// app/Models/OrderItem.php
class OrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
```

Supported relationship types: `hasMany`, `hasManyThrough`, `morphMany`, or any relationship that returns a query builder.

## Configuration

### Expand / Collapse

```php
$table->subRows('items')
    ->subRowsExpandable()                    // Users can toggle rows (default: true)
    ->subRowsDefaultExpanded()               // Start all rows expanded
    ->subRowsDefaultExpanded(false)          // Start all rows collapsed (default)
```

When `subRowsExpandable` is enabled, a toggle chevron button appears on each row. The toolbar also shows **Expand All** / **Collapse All** buttons.

### Toggle Label

```php
$table->subRows('items')
    ->subRowsToggleLabel('Show items')       // Custom label for the toggle column
```

### Limit Visible Sub-Rows

```php
$table->subRows('items')
    ->subRowsLimit(5)                        // Show max 5 sub-rows per parent
    ->subRowsLimit(null)                     // Show all (default)
```

### Custom Sub-Row Query

Modify ordering, scopes, or conditions on the child records:

```php
$table->subRows('items')
    ->subRowQuery(fn (Builder $query) => $query
        ->orderBy('sort_order')
        ->where('active', true)
    )
```

### Custom Blade View

Override the default sub-row template:

```php
$table->subRows('items')
    ->subRowView('components.custom-sub-row')
```

Your custom view receives these variables:

| Variable | Type | Description |
|----------|------|-------------|
| `$table` | `Table` | The table instance |
| `$component` | `Component` | The Livewire component |
| `$record` | `Model` | The parent record |
| `$recordKey` | `string` | The parent record primary key |
| `$subRows` | `Collection` | The child records |
| `$colSpan` | `int` | Total column span for the row |
| `$cellPadding` | `string` | CSS padding class |
| `$isBordered` | `bool` | Whether table has borders |

## Flatten Mode

Flatten mode removes the parent/child hierarchy and displays all sub-rows as regular table rows. This is useful for exports or "show everything" views.

```php
$table->subRows('items')
    ->flattenSubRows()                       // Start in flatten mode
    ->flattenSubRows(false)                  // Start in grouped mode (default)
```

Users can toggle between grouped and flattened views using the toolbar button. The Livewire component tracks this via the `$flattenMode` property.

## Sub-Row Filtering

Enable independent filtering within sub-rows:

```php
$table->subRows('items')
    ->subRowsFilterable()
```

When enabled, filter inputs appear above the sub-row table. Filters are applied per-column based on each sub-row column's `isFilterable()` and `applyFilter()` methods.

The active filters are stored in the `$subRowFilters` Livewire property. A **Reset** button appears when filters are active.

## Sub-Row Summaries

Sub-row columns support summary/footer aggregations:

```php
$table->subRows('items')
    ->subRowColumns([
        TextColumn::make('product_name'),

        TextColumn::make('quantity')
            ->numeric()
            ->summary(fn (Collection $records) => $records->sum('quantity')),

        TextColumn::make('unit_price')
            ->money(),

        TextColumn::make('line_total')
            ->money()
            ->summary(fn (Collection $records) => $records->sum('line_total')),
    ])
```

Summary rows appear in the `<tfoot>` of each sub-row table, showing aggregated values for the child records of that specific parent.

## Livewire Component Methods

The `WithTable` trait adds these methods to your Livewire component:

### Toggle Expansion

```php
// Toggle a single row
$this->toggleRowExpansion($recordKey);

// Expand all visible rows
$this->expandAllRows();

// Collapse all rows
$this->collapseAllRows();

// Check if a row is expanded
$this->isRowExpanded($recordKey);  // returns bool
```

### Flatten Mode

```php
$this->toggleFlattenMode();
```

### Sub-Row Data

```php
// Get sub-rows for a parent record (applies query modifier, filters, and limit)
$subRows = $this->getSubRows($record);

// Reset all sub-row filters
$this->resetSubRowFilters();
```

### Summaries

```php
// Compute summaries for sub-rows of a specific parent
$summaries = $this->computeTableSummaries('subRows', $parentRecord);
```

## Livewire Properties

```php
public array $expandedRows = [];         // Currently expanded row keys
public bool $flattenMode = false;        // Whether flatten mode is active
public array $subRowFilters = [];        // Active sub-row filters [columnName => value]
```

## Toolbar

When sub-rows are enabled, the table automatically renders a toolbar with:

- **Expand All** / **Collapse All** buttons (when `subRowsExpandable` is `true`)
- **Flatten Mode** toggle button (switches between "Grouped View" and "Show All")

## Complete Example

```php
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\BooleanColumn;

public function table(Table $table): Table
{
    return $table
        ->model(Invoice::class)
        ->columns([
            TextColumn::make('invoice_number')
                ->sortable()
                ->searchable(),

            TextColumn::make('customer.name')
                ->label('Customer')
                ->searchable(),

            TextColumn::make('issued_at')
                ->date(),

            BadgeColumn::make('status')
                ->colors([
                    'draft' => 'gray',
                    'sent' => 'info',
                    'paid' => 'success',
                    'overdue' => 'danger',
                ]),

            TextColumn::make('total')
                ->money()
                ->sortable()
                ->summary(fn ($records) => $records->sum('total')),
        ])

        // Sub-rows: invoice line items
        ->subRows('items')
        ->subRowColumns([
            TextColumn::make('description')
                ->searchable(),

            TextColumn::make('quantity')
                ->numeric()
                ->summary(fn ($records) => $records->sum('quantity')),

            TextColumn::make('unit_price')
                ->money(),

            TextColumn::make('vat_rate')
                ->formatStateUsing(fn ($value) => ($value * 100) . '%'),

            TextColumn::make('line_total')
                ->money()
                ->summary(fn ($records) => $records->sum('line_total')),
        ])
        ->subRowQuery(fn ($query) => $query->orderBy('sort_order'))
        ->subRowsExpandable()
        ->subRowsDefaultExpanded(false)
        ->subRowsFilterable()
        ->subRowsLimit(50)
        ->subRowsToggleLabel('Items')
        ->defaultSort('issued_at', 'desc');
}
```
