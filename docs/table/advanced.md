# Advanced Features

---

## Table of Contents

1. [Sub-Rows (Expandable Rows)](#sub-rows-expandable-rows)
2. [Summary Footer (Aggregates)](#summary-footer-aggregates)
3. [Polling (Auto-Refresh)](#polling-auto-refresh)
4. [Lazy Loading](#lazy-loading)
5. [Performance Optimization](#performance-optimization)
6. [Query Debugging](#query-debugging)
7. [SQL Debug](#sql-debug)
8. [Responsive Layout](#responsive-layout)
9. [Column Toggling](#column-toggling)
10. [Notifications Per-Table](#notifications-per-table)
11. [URL State Persistence](#url-state-persistence)
12. [Custom Views](#custom-views)

---

## Sub-Rows (Expandable Rows)

The `HasSubRows` trait enables expandable child rows for hierarchical data — orders → items, categories → products, departments → employees.

### Basic Sub-Rows

```php
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;

$table
    ->model(Order::class)
    ->columns([
        TextColumn::make('number')->searchable()->sortable(),
        TextColumn::make('customer.name')->searchable(),
        TextColumn::make('total')->money('CZK')->sortable(),
        BadgeColumn::make('status')->colors([...]),
    ])
    ->subRows(fn (Order $record) => $record->items)
    ->subRowColumns([
        TextColumn::make('product.name'),
        TextColumn::make('quantity')->alignRight(),
        TextColumn::make('unit_price')->money('CZK'),
        TextColumn::make('subtotal')->money('CZK')->weight('bold'),
    ])
```

Users see a chevron icon on the left. Clicking expands the row to show child rows below.

### Expand All by Default

```php
$table->subRowsDefaultExpanded()
```

All rows start expanded.

### Flatten Mode

Show all sub-rows inline without expand/collapse — flat view:

```php
$table->flattenSubRows()
```

Users can toggle flatten mode via `toggleFlattenMode()` Livewire method.

### Custom Expand Icon

```php
$table->subRowIcon('chevron-down')     // default: 'chevron-right' → rotates on expand
```

### Sub-Row Relation

```php
// Explicit relation name (instead of Closure)
$table->subRowRelation('items')

// With eager loading
$table->subRowRelation('items.product')
```

### Independent Sub-Row Filtering

```php
$table->subRowFilters([
    SelectFilter::make('status')
        ->options(['pending' => 'Pending', 'shipped' => 'Shipped', 'delivered' => 'Delivered']),
])
```

Sub-row filters are stored in `$subRowFilters` and apply only to the child records.

### Custom Sub-Row View

Instead of sub-row columns, render a completely custom Blade view:

```php
$table->subRowView('components.order-items-detail', [
    'showTotals' => true,
    'currency' => 'CZK',
])
```

```blade
{{-- resources/views/components/order-items-detail.blade.php --}}
<div class="p-4 bg-gray-50">
    <table class="w-full text-sm">
        @foreach($record->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td class="text-right">{{ $item->quantity }}×</td>
                <td class="text-right font-bold">
                    {{ number_format($item->subtotal, 2) }} {{ $currency }}
                </td>
            </tr>
        @endforeach
        @if($showTotals)
            <tr class="border-t font-bold">
                <td colspan="2">Total</td>
                <td class="text-right">{{ number_format($record->total, 2) }} {{ $currency }}</td>
            </tr>
        @endif
    </table>
</div>
```

### Sub-Row Livewire State

| Property | Type | Description |
|----------|------|-------------|
| `$expandedRows` | `array` | Keys of expanded parent records |
| `$flattenMode` | `bool` | Flat view toggle |
| `$subRowFilters` | `array` | Filter values for sub-rows |

### Sub-Rows API

```php
->subRows(Closure $fn)                   // fn($record) => Collection|array
->subRowRelation(string $relation)       // Eloquent relation name
->subRowColumns(array $columns)          // Column[] for sub-rows
->subRowView(string $view, array $data)  // custom Blade view
->subRowFilters(array $filters)          // Filter[] for sub-rows
->subRowsDefaultExpanded(bool $expanded = true)
->flattenSubRows(bool $flatten = true)
->subRowIcon(string $icon)               // expand/collapse icon
->hasSubRows(): bool
->getSubRowColumns(): array
```

---

## Summary Footer (Aggregates)

The `HasSummary` trait adds aggregate footer rows — sum, avg, count, min, max, range.

### Column-Level Summary

```php
TextColumn::make('amount')
    ->money('CZK')
    ->summarize('sum', 'Total')

TextColumn::make('price')
    ->money('CZK')
    ->summarize('avg', 'Average')

TextColumn::make('id')
    ->summarize('count', 'Records')

TextColumn::make('rating')
    ->numeric(decimalPlaces: 1)
    ->summarize('min', 'Lowest')

TextColumn::make('score')
    ->numeric()
    ->summarize('max', 'Highest')

TextColumn::make('salary')
    ->money('CZK')
    ->summarize('range')          // shows "min - max"
```

### Table-Level Summary

```php
$table
    ->summarizeSum('amount', 'Total Amount')
    ->summarizeAvg('price', 'Avg Price')
    ->summarizeCount('id', 'Total Records')
    ->summarizeMin('rating', 'Min Rating')
    ->summarizeMax('score', 'Max Score')
    ->summarizeRange('salary', 'Salary Range')
```

### Summary Scopes

By default, summaries are computed over the **current page** of results (in-memory). For a **full query** aggregate (additional DB query):

```php
TextColumn::make('amount')
    ->money('CZK')
    ->summarize('sum', 'Page Total')           // current page (default)
    ->summaryQuery('sum', 'Grand Total')       // full query aggregate
```

### Custom Summary Formatting

```php
TextColumn::make('revenue')
    ->summarize('sum')
    ->summaryFormatUsing(fn (float $value) => number_format($value, 0, ',', ' ') . ' CZK')
```

### How It Works

1. **Page-level**: After query results are fetched, `HasSummary` iterates the Collection and computes aggregates in PHP
2. **Query-level**: A separate `$query->sum('amount')` (or avg/count/min/max) query is executed against the filtered (but unpaginated) dataset

### Summary API

```php
// Column-level
->summarize(string $aggregate, ?string $label = null)
->summaryQuery(string $aggregate, ?string $label = null)
->summaryFormatUsing(Closure $fn)

// Table-level shortcuts
->summarizeSum(string $column, ?string $label = null)
->summarizeAvg(string $column, ?string $label = null)
->summarizeCount(string $column, ?string $label = null)
->summarizeMin(string $column, ?string $label = null)
->summarizeMax(string $column, ?string $label = null)
->summarizeRange(string $column, ?string $label = null)
```

---

## Polling (Auto-Refresh)

Wire Table supports two polling modes: **table-level** (refreshes entire table) and **row/column-level** (refreshes specific cells via `PollColumn`).

### Table-Level Polling

```php
$table->poll('5s')                       // refresh every 5 seconds
```

Supported intervals: `'1s'`, `'2s'`, `'3s'`, `'5s'`, `'10s'`, `'15s'`, `'30s'`, `'60s'`.

### Keep Alive (Background Tabs)

```php
$table->poll('5s')->pollKeepAlive()
```

By default, Livewire stops polling when the browser tab is hidden. `pollKeepAlive()` overrides this.

### Only Visible (Viewport)

```php
$table->poll('5s')->pollOnlyVisible()
```

Only poll when the table element is in the viewport (uses IntersectionObserver).

### Conditional Polling

```php
$table->poll('5s')
      ->pollWhen(fn () => Job::where('status', 'running')->exists())
```

Polling starts/stops based on the condition. Checked on each interval.

### Custom Poll Method

```php
$table->poll('10s')->pollMethod('refreshData')
```

Instead of full re-render, calls a specific Livewire method.

### Row/Column Polling

Use `PollColumn` for per-cell live updates without refreshing the entire table:

```php
PollColumn::make('job_status')
    ->interval('3s')
    ->stateDisplays([...])
    ->stopWhen(fn ($state) => $state === 'completed')
    ->rowLevelPolling()
```

See [Columns — PollColumn](columns.md#pollcolumn) for the complete PollColumn API.

### Polling API

```php
->poll(string|Closure $interval)         // interval string or Closure returning ?string
->pollKeepAlive(bool $keepAlive = true)
->pollOnlyVisible(bool $onlyVisible = true)
->pollWhen(Closure $condition)           // fn() => bool
->pollMethod(string $method)             // Livewire method name
```

---

## Lazy Loading

Defers the initial table render for faster page load. The table loads asynchronously after the page is visible.

```php
$table->lazy()
```

### Custom Placeholder

```php
$table->lazy()
      ->lazyPlaceholder(
          '<div class="flex items-center justify-center p-16 text-gray-400">
              <svg class="w-8 h-8 animate-spin" ...>...</svg>
              <span class="ml-3">Loading table...</span>
          </div>'
      )
```

### How It Works

1. Page renders immediately with the placeholder HTML
2. Livewire dispatches an async call to load table content
3. Placeholder is replaced with the fully rendered table
4. Subsequent interactions (sort, filter, paginate) are normal Livewire calls

### When to Use

- Dashboard pages with multiple tables — load each lazily
- Tables with complex queries — don't block initial paint
- Below-the-fold tables — load only when scrolled to (combine with `pollOnlyVisible`)

---

## Performance Optimization

### Simple Pagination

Eliminates the `COUNT(*)` query:

```php
$table->simplePagination()
```

Trade-offs:
- No "Showing X of Y" text
- No page number links (only Previous / Next)
- Saves one query per page load on large tables

### Cursor Pagination

Offset-free, constant-time pagination:

```php
$table->cursorPagination()
```

Requirements:
- Table must have a unique, orderable column (usually `id` or `created_at`)
- Default sort must be set

Trade-offs:
- No random page access (Previous / Next only)
- URL cursors are opaque strings
- Cannot combine with `count()` operations

Best for: real-time data feeds, infinite scroll UIs, tables > 1M rows.

### Query Caching

Cache query results for a configured TTL:

```php
$table->cacheQuery(ttl: 60)                    // 60 seconds, auto-generated key
$table->cacheQuery(ttl: 300, key: 'users')     // 5 minutes, custom key
```

Cache key includes a hash of the current state (search, filters, sort, page), so different states cache independently.

Uses `Cache::remember()` — works with any Laravel cache driver.

### Chunked Bulk Processing

Process records in batches for memory-efficient bulk operations:

```php
$table->chunk(500, function (Collection $records) {
    foreach ($records as $record) {
        $record->process();
    }
})
```

Uses `chunkById()` internally for consistent ordering.

### Performance Comparison

| Feature | Queries | Best For |
|---------|---------|----------|
| Standard pagination | 2 (count + select) | < 100k rows |
| Simple pagination | 1 (select) | 100k – 1M rows |
| Cursor pagination | 1 (select) | > 1M rows |
| Cached + standard | 0-2 (cache hit/miss) | Frequently viewed, rarely updated |
| Lazy loading | Same as above (deferred) | Faster initial paint |

---

## Query Debugging

### QueryPlan Inspection

Get the immutable `QueryPlan` to see exactly what the engine will do:

```php
$plan = $table->debugQueryPlan();

// Joins
foreach ($plan->joins as $join) {
    echo "{$join->type} JOIN {$join->table} ON {$join->first} {$join->operator} {$join->second}\n";
}

// Eager loads
dump($plan->eagerLoads);     // ['author', 'tags', 'category']

// Aggregates
dump($plan->aggregates);      // [AggregateClause(relation: 'comments', function: 'count')]

// Filters
dump($plan->filters);         // [FilterClause(column: 'role', operator: '=', value: 'admin')]

// Search
dump($plan->searchClauses);   // [SearchClause(columns: ['name','email'], term: 'john')]

// Sorts
dump($plan->sortClauses);     // [SortClause(column: 'name', direction: 'asc')]
```

### Raw SQL

```php
$sql = $table->toSql();
// "SELECT users.* FROM users LEFT JOIN departments ON ... WHERE ... ORDER BY ..."
```

### Column Metadata

```php
$info = $table->getColumnsInfo();
// Array of column metadata: DB type, nullable, capabilities, relation paths

$dbColumns = $table->getDatabaseColumns();
// ['id', 'name', 'email', 'role', 'created_at', ...]

$dbInfo = $table->getDatabaseColumnsInfo();
// ['name' => ['type' => 'varchar', 'nullable' => false, ...], ...]
```

---

## SQL Debug

The `HasSqlDebug` trait (included in `WithTable`) provides SQL interpolation utilities:

```php
// Get raw SQL with bindings interpolated (for debugging only!)
$rawSql = $this->builderToSql($query);
// "SELECT * FROM users WHERE role = 'admin' AND created_at >= '2024-01-01'"

// Interpolate bindings into a prepared statement
$interpolated = $this->interpolateSql($sql, $bindings);
```

**Warning**: Interpolated SQL is for debugging only. Never execute it directly — use parameterized queries.

### Development Usage

```php
class UserTable extends Component
{
    use WithTable;

    public function debugQuery(): void
    {
        $table = $this->table(Table::make());
        $query = $this->buildTableQuery($table);

        logger()->debug('Table SQL', [
            'sql' => $this->builderToSql($query),
            'plan' => $table->debugQueryPlan(),
        ]);
    }
}
```

---

## Responsive Layout

### Stacked on Mobile

Below a breakpoint, columns stack vertically as label-value pairs:

```php
$table->stackedOnMobile()
      ->stackedBreakpoint('md')    // stack below 'md' (default)
```

In stacked mode:
- Each row becomes a card
- Each column renders as `Label: Value`
- Column `visibleFrom()`/`hiddenFrom()` still applies

### Column Breakpoints

```php
// Visible from md up (hidden on mobile)
TextColumn::make('email')->visibleFrom('md')

// Hidden from lg up (visible only on mobile/tablet)
TextColumn::make('phone')->hiddenFrom('lg')

// Shortcuts
TextColumn::make('address')->onlyOnDesktop()       // ≥lg
TextColumn::make('avatar')->onlyOnMobile()          // <md
TextColumn::make('subtitle')->onlyOnTabletAndUp()   // ≥md
TextColumn::make('metadata')->onlyOnLargeScreens()  // ≥xl
```

### Per-Record Mobile Display

```php
TextColumn::make('user')
    ->mobileDisplayUsing(fn ($record) => $record->name)
    ->desktopDisplayUsing(fn ($record) => "{$record->name} ({$record->email})")
```

---

## Column Toggling

Users can show/hide toggleable columns via a column picker dropdown:

```php
// Mark specific columns as toggleable
TextColumn::make('phone')
    ->toggleable()                  // user can hide/show
    ->hidden()                      // start hidden (user can enable)

TextColumn::make('notes')
    ->toggleable()
    ->visibleFrom('lg')             // default visible from lg, but user can override
```

State is stored in `$hiddenColumns` (Livewire property). Persists for the session.

---

## Notifications Per-Table

Override the global notification driver for a specific table:

```php
$table->notificationDriver('livewire')   // use Livewire events for this table
```

Useful when different parts of your app use different notification UIs.

---

## URL State Persistence

Persist table state (search, sort, filters, page) in the URL for bookmarkable/shareable links:

```php
class UserTable extends Component
{
    use WithTable;

    protected $queryString = [
        'tableSearch' => ['except' => '', 'as' => 'q'],
        'tableSortColumn' => ['except' => '', 'as' => 'sort'],
        'tableSortDirection' => ['except' => 'asc', 'as' => 'dir'],
        'tablePerPage' => ['except' => 10, 'as' => 'per_page'],
        'tableFilters' => ['except' => [], 'as' => 'filters'],
    ];
}
```

Now URLs look like: `/users?q=john&sort=name&dir=asc&per_page=25&filters[role]=admin`

---

## Custom Views

### Custom Table View

```php
$table->view('my-custom-table-view')
```

Wire Table resolves views with namespace support. You can publish and override the default views:

```bash
php artisan vendor:publish --tag=wire-table-views
```

Published to `resources/views/vendor/wire-table/`.

### HasView Trait

The `HasView` trait provides view resolution logic:

```php
// Resolves in order:
// 1. Explicit view set via ->view()
// 2. Package view: wire-table::table
$table->getView();
```

---

## Complete Real-World Example

```php
class OrderTable extends Component
{
    use WithTable;

    protected $queryString = [
        'tableSearch' => ['except' => '', 'as' => 'q'],
        'tableSortColumn' => ['except' => '', 'as' => 'sort'],
        'tableFilters' => ['except' => [], 'as' => 'f'],
    ];

    public function table(Table $table): Table
    {
        return $table
            ->model(Order::class)
            ->modifyQueryUsing(fn ($q) => $q->where('tenant_id', auth()->user()->tenant_id))
            ->columns([
                TextColumn::make('number')
                    ->fontFamily('mono')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                StackedColumn::make('customer')
                    ->avatar('customer.avatar_url')
                    ->primary('customer.name')
                    ->secondary('customer.email')
                    ->circular()
                    ->searchable()
                    ->searchColumns(['customer.name', 'customer.email']),

                TextColumn::make('items.count')
                    ->label('Items')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('total')
                    ->money('CZK')
                    ->sortable()
                    ->alignRight()
                    ->weight('bold')
                    ->summarize('sum', 'Page Total')
                    ->summaryQuery('sum', 'Grand Total'),

                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'shipped',
                        'primary' => 'delivered',
                        'danger' => 'cancelled',
                    ])
                    ->icons([
                        'clock' => 'pending',
                        'refresh' => 'processing',
                        'truck' => 'shipped',
                        'check' => 'delivered',
                        'x' => 'cancelled',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->size('sm')
                    ->textColor('gray')
                    ->visibleFrom('lg'),

                PollColumn::make('shipping_status')
                    ->interval('30s')
                    ->badge()
                    ->colors(['success' => 'delivered', 'info' => 'in_transit', 'gray' => 'waiting'])
                    ->pollWhile(fn ($state) => $state === 'in_transit')
                    ->visibleFrom('md'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ])
                    ->multiple()
                    ->default(['pending', 'processing']),

                DateFilter::make('created_at')
                    ->from()->until()
                    ->fromLabel('From')
                    ->untilLabel('Until'),

                NumberRangeFilter::make('total')
                    ->min(0)->max(1000000)->step(100),

                TernaryFilter::make('has_invoice')
                    ->label('Invoice Generated')
                    ->trueQuery(fn ($q) => $q->whereNotNull('invoice_id'))
                    ->falseQuery(fn ($q) => $q->whereNull('invoice_id')),
            ])
            ->actions([
                Action::make('view')
                    ->icon('eye')
                    ->url(fn ($r) => route('orders.show', $r)),

                ActionGroup::make('more', [
                    Action::make('invoice')
                        ->icon('document')
                        ->visible(fn ($r) => $r->status !== 'draft')
                        ->action(fn ($r) => $r->generateInvoice()),
                    Action::make('duplicate')
                        ->icon('copy')
                        ->action(fn ($r) => $r->replicate()->save()),
                    Action::divider(),
                    Action::make('cancel')
                        ->icon('x')
                        ->color('danger')
                        ->visible(fn ($r) => ! in_array($r->status, ['delivered', 'cancelled']))
                        ->requiresConfirmation()
                        ->modalHeading('Cancel this order?')
                        ->action(fn ($r) => $r->cancel()),
                ]),
            ])
            ->bulkActions([
                BulkAction::make('export')
                    ->icon('download')
                    ->action(fn ($records) => $this->export($records)),
                DeleteBulkAction::make(),
            ])
            ->headerActions([
                HeaderAction::make('create')
                    ->label('New Order')
                    ->icon('plus')
                    ->url(route('orders.create')),
            ])
            ->subRows(fn ($record) => $record->items)
            ->subRowColumns([
                TextColumn::make('product.name'),
                TextColumn::make('quantity')->alignCenter(),
                TextColumn::make('unit_price')->money('CZK'),
                TextColumn::make('subtotal')->money('CZK')->weight('bold'),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->searchDebounce(400)
            ->paginated()
            ->perPage(25)
            ->perPageOptions([10, 25, 50, 100])
            ->selectable()
            ->striped()
            ->hoverable()
            ->stackedOnMobile()
            ->emptyStateHeading('No orders found')
            ->emptyStateDescription('Create your first order to get started.')
            ->emptyStateIcon('shopping-cart');
    }
}
```
