---
order: 60
---

# Sub-Rows

Sub-rows render related child records below each parent row. Use them when users need detail without leaving the table.

## Basic Setup

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

public function table(Table $table): Table
{
    return $table
        ->model(Order::class)
        ->columns([
            TextColumn::make('order_number')->sortable(),
            TextColumn::make('customer.name')->label('Customer'),
            TextColumn::make('total')->money(),
        ])
        ->subRows('items')
        ->subRowColumns([
            TextColumn::make('product_name'),
            TextColumn::make('quantity')->numeric(),
            TextColumn::make('unit_price')->money(),
        ]);
}
```

`subRows('items')` expects an Eloquent relationship method on the parent model.

## When to Use Them

Use sub-rows when:

- a parent record owns a small set of child records
- users need quick drill-down without route changes
- the child data shares the same decision context as the parent row

Avoid them when the child data is large enough that it deserves its own table, filters, or pagination.

## Expand and Collapse

```php
->subRowsExpandable()
->subRowsDefaultExpanded()
->subRowsToggleLabel('Show items')
```

The table tracks expanded rows in Livewire state, so the user can open only the records they care about.

## Filter and Limit Child Rows

```php
->subRowsLimit(5)
->subRowQuery(fn (Builder $query) => $query
    ->where('active', true)
    ->orderBy('sort_order')
)
```

Use `subRowsLimit()` when the child relationship can be noisy. Use `subRowQuery()` when the UI should only show a filtered slice of the relationship.

## Flatten Mode

Flatten mode removes the hierarchy and shows all child rows inline.

```php
->flattenSubRows()
```

This is useful for review workflows where grouping is less important than scanning all detail rows together.

## Summaries

Sub-row columns can render per-parent summaries.

```php
->subRowColumns([
    TextColumn::make('product_name'),
    TextColumn::make('quantity')
        ->numeric()
        ->summarizeSum(scope: 'subRows'),
    TextColumn::make('line_total')
        ->money()
        ->summarizeSum(scope: 'subRows'),
])
```

## Custom View

Replace the default sub-row renderer when you need a custom layout.

```php
->subRowView('components.orders.sub-rows')
```

Use a custom view when the child records are not naturally tabular or when you need domain-specific formatting.

## Related Docs

- [Table Overview](overview.md)
- [Columns](columns.md)
- [Advanced Features](advanced.md)
