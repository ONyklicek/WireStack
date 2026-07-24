---
order: 80
---

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
10. [Row Context Menu](#row-context-menu)
11. [Notifications Per-Table](#notifications-per-table)
12. [URL State Persistence](#url-state-persistence)
13. [Browser Testing Selectors](#browser-testing-selectors)
14. [Custom Views](#custom-views)

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
    ->subRows('items')
    ->subRowColumns([
        TextColumn::make('product.name'),
        TextColumn::make('quantity')->alignRight(),
        TextColumn::make('unit_price')->money('CZK'),
        TextColumn::make('subtotal')->money('CZK')->weight('bold'),
    ])
```

Users see a chevron icon on the left. Clicking expands the row to show child rows below.

### Expansion Baseline

`subRowsDefaultExpanded()` sets where rows *start*; the master chevron in the
expander column header moves that baseline at runtime, and the choice outlives
pagination:

```php
$table->subRowsDefaultExpanded()
```

`flattenSubRows()` is a deprecated alias for the same thing — it never flattened
anything, it only opened every row. `toggleFlattenMode()` still works and now
calls `toggleAllRowExpansion()`.

### Sub-Row Relation with Eager Loading

`->subRows()` accepts dot-notation for eager-loaded relations:

```php
$table->subRows('items.product')
```

### Independent Sub-Row Filtering

```php
$table->subRowsFilterable()
```

When enabled, the table renders separate filter controls for sub-rows alongside the main filters.

### Custom Sub-Row View

Instead of sub-row columns, render a completely custom Blade view:

```php
$table->subRowView('components.order-items-detail')
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
| `$flattenMode` | `bool\|null` | Expansion baseline (deprecated alias of `rows.expandAll`) |

### Sub-Rows API

```php
->subRows(string $relation)              // Eloquent relation name (dot notation supported)
->subRowColumns(array $columns)          // Column[] for sub-rows
->subRowView(string $view)              // custom Blade view (replaces columns)
->subRowsFilterable(bool $filterable = true)
->subRowsDefaultExpanded(bool $expanded = true)
->subRowsExpandable(bool $expandable = true)
->subRowsLimit(?int $limit)             // max sub-rows before "show more"
->subRowsToggleLabel(?string $label)
->flattenSubRows(bool $flatten = true)   // deprecated: subRowsDefaultExpanded()
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

The `scope` argument (3rd parameter of `summarize()`) selects which rows are
aggregated. It defaults to `'query'` (all filtered rows, via a DB aggregate).
Pass `'page'` to aggregate only the current page in memory. A column can carry
more than one summary:

```php
TextColumn::make('amount')
    ->money('CZK')
    ->summarize('sum', 'Page Total', scope: 'page')    // current page only
    ->summarize('sum', 'Grand Total', scope: 'query')  // all filtered rows (default)
```

Scopes: `'query'` (all filtered), `'page'` (current page), `'selection'`
(selected rows), `'subRows'`.

### Custom Summary Formatting

Pass a `format` closure to `summarize()`, or use `summaryDecimals()` for numeric
formatting:

```php
TextColumn::make('revenue')
    ->summarize('sum', format: fn (float $value) => number_format($value, 0, ',', ' ') . ' CZK')

TextColumn::make('total')
    ->summarize('sum')
    ->summaryDecimals(2)                 // → "1 234,50"
```

### How It Works

1. **Page scope**: after results are fetched, `HasSummary` iterates the
   Collection and computes the aggregate in PHP.
2. **Query scope**: a separate `$query->sum('amount')` (or avg/count/min/max) is
   executed against the filtered (but unpaginated) dataset.

### Summary API

These methods live on the **column** (`HasSummary`):

```php
->summarize(
    string|Closure $type,           // 'sum','avg','count','min','max','range','distinct','median'
    ?string $label = null,
    string $scope = 'query',         // 'query' | 'page' | 'selection' | 'subRows'
    ?Closure $format = null,         // fn(mixed $value): string
    ?Closure $when = null,           // fn(Builder $query): Builder
)
->summaryDecimals(int $decimals, string $decimalSeparator = ',', string $thousandsSeparator = ' ')

// Shortcuts — each takes (?string $label = null, string $scope = 'query'):
->summarizeSum()      ->summarizeAvg()     ->summarizeCount()
->summarizeMin()      ->summarizeMax()     ->summarizeRange()
->summarizeDistinct() ->summarizeMedian()
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

### Change Detection (Skip Unchanged Renders)

```php
$table->poll('5s')->pollChangeDetection()
```

Each poll normally re-runs the full query, summaries, and DOM morph even when
nothing changed. With change detection enabled, a cheap checksum
(`COUNT(*)` + `MAX(updated_at)` of the filtered query, one SQL query) is
compared between polls — an unchanged checksum skips the render entirely.

Models without timestamps fall back to always rendering. When parent
timestamps don't capture relevant changes (e.g. rollup sums over child rows),
provide a custom checksum:

```php
$table->poll('5s')
      ->pollChangeDetection(fn ($query) => (string) $query->max('synced_at'))
```

The closure receives the filtered query (without ordering) and must return a
string that changes whenever a re-render is needed.

### Row/Column Polling

Use `PollColumn` for per-cell live updates without refreshing the entire table:

```php
PollColumn::make('job_status')
    ->interval('3s')
    ->stateDisplays([...])
    ->stopWhen(fn ($state) => $state === 'completed')
    ->rowLevelPolling()
```

See [Columns — PollColumn](columns/poll.md) for the complete PollColumn API.

### Polling API

```php
->poll(string|Closure $interval)         // interval string or Closure returning ?string
->pollKeepAlive(bool $keepAlive = true)
->pollOnlyVisible(bool $onlyVisible = true)
->pollWhen(Closure $condition)           // fn() => bool
->pollMethod(string $method)             // Livewire method name
->pollChangeDetection(bool|Closure $detector = true) // skip render when data unchanged
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

A cache key is two parts: a **namespace** saying which table this is, and a
**state fingerprint** saying which view of it. The namespace is the query's SQL
and bindings by default, or whatever you pass as `key:`. The fingerprint covers
search, filters, column filters, sort, per-page and the page number, and is
appended to *every* namespace — a custom `key:` scopes entries, it does not
replace their identity.

That matters because a cached table serves a paginated *slice*, not a query:
`perPage` and the page are applied inside the cached callback, so they never
reach the SQL, and a custom key knows nothing about the sort or the active
filters. If any of those were missing from the key, the table would freeze for
the whole TTL — changing the page size would keep serving the rows cached under
the same key.

To scope entries by tenant or user, either pass `key:` or override
`generateQueryCacheKey()` on the component; the state fingerprint is appended
either way.

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
$table->stackedOnMobile(true, 'md')   // 2nd arg = breakpoint to stack below (default 'md')
```

In stacked mode:
- Each row becomes a card
- Each column renders as `Label: Value`
- Column `visibleFrom()`/`hiddenFrom()` still applies

Row actions render inline in each card header. When a row has several actions,
collapse them into a single dropdown group so the header stays tidy:

```php
$table
    ->stackedOnMobile()
    ->collapseActionsOnMobile()   // one "⋮" trigger per card instead of inline buttons
```

The collapse only kicks in once a row has **3 or more** actions; with fewer, the
card keeps them inline. Tune the threshold with the second argument:

```php
->collapseActionsOnMobile(threshold: 2)   // collapse from 2 actions up
->collapseActionsOnMobile(threshold: 1)   // always collapse
```

Only the mobile stacked cards are affected — the desktop table keeps its inline
action buttons. Any existing `ActionGroup`s are flattened into the single mobile
dropdown (dividers are dropped in the merge), and a card with only one visible
action still shows that action inline. The dropdown inherits the table's
`sheetOnMobile()` / `mobileBreakpoint()` settings (bottom-sheet on small screens
by default).

### The Card's Anatomy

A card is a record, not the column order in disguise. Five named slots carry the
hierarchy — what this is, whose it is, how much — and the rest drops into the
label/value grid below:

```text
┌──────────────────────────────────────────────┐
│ INV-1001                        9 350 Kč  ⋮  │  title · metric · actions
│ Northwind Traders                            │  subtitle
│ [ paid ]                                     │  meta
│ ─────────────────────────────────────────    │
│ NOTE            REFERENCE                    │  everything else
│ First order     2026/114                     │
└──────────────────────────────────────────────┘
```

Nothing has to be declared for this: the slots are derived from the columns you
already have.

| Slot | Derived from |
| ---- | ------------ |
| `title` | the first visible column |
| `metric` | the last right-aligned column — what `money()` and `numeric()` produce |
| `meta` | badge columns |
| `subtitle` | the first column no other slot claimed |
| detail grid | everything left |

When the derivation guesses wrong, say so — per column:

```php
TextColumn::make('total')->money()->mobileMetric(),
BadgeColumn::make('status')->mobileMeta(),
TextColumn::make('reference')->mobileDetail(),   // keep it out of the header
```

…or for the whole table, which wins over both derivation and per-column calls:

```php
use NyonCode\WireTable\Support\MobileCardConfig;

$table->mobileCard(fn (MobileCardConfig $card) => $card
    ->title('number')
    ->subtitle('customer')
    ->metric('total')
    ->meta(['status', 'due_at']));
```

The metric is set right on the title line in tabular figures, so a column of
amounts can be compared down the edge instead of being read one card at a time.

### Sub-Rows on a Card

Expanded children render as a list rather than the desktop's nested table: name
on the left, its figure on the same right edge as the card's own metric, the
supporting detail underneath.

```text
│ 3 items                                   ⌄  │
│ ──────────────────────────────────────────── │
│ 27" monitor                    5 600 Kč   ⋮  │
│ Unit: 5 600 Kč                               │
│ Mechanical keyboard            2 400 Kč   ⋮  │
│ Unit: 1 200 Kč                               │
│ Subtotal                       9 350 Kč      │
```

Per-parent subtotals, the "Show N more" affordance and per-child actions all
work here — they used to be desktop-only, while the card flattened every child
into one indistinguishable grid.

Child actions always collapse into a single `⋮` trigger, whatever
`collapseActionsOnMobile()` says: a child line is narrower than the card holding
it, and two labelled buttons there crush the product name to an ellipsis.

The collapsed toggle names the child count (`3 items`) when the number is already
in memory, and falls back to `Details` when it is not — a collapsed row has no
eager-loaded children, so counting would cost one query per card. Add
`->withCount('items')` to the base query and every card names its count for free.

### Totals on a Card

The desktop totals live in a `<tfoot>` of the table the card layout hides, so a
stacked table used to show no totals at all — in an accounting table, the number
the user came for. They now render below the cards as label/value rows, on the
same right edge as each card's metric, with the same *All / This page /
Selection* scope toggle the desktop footer has:

```text
│ INV-1003                          8 450 Kč │
├────────────────────────────────────────────┤
│ Showing:                  [ All ][This page]│
│ Total items · Items                       7 │
│ Grand total · Total              35 900 Kč  │
│ Average · Total                  11 967 Kč  │
```

Nothing to configure — a column with `summarize*()` gets its total here as well
as in the table footer, and sub-row grand totals follow the same way.

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

By default the shown/hidden set lives only for the component's lifetime (it
resets on a full page reload).

### Remember each user's layout

Call `rememberColumns()` with a stable key and the table loads the current
user's saved layout on mount and persists it whenever a column is toggled — so
every user keeps their own column arrangement across reloads. A "Reset columns"
control appears in the picker to return to the configured defaults.

```php
$table
    ->columns([
        TextColumn::make('name'),
        TextColumn::make('email')->toggleable(),
        TextColumn::make('phone')->toggleable()->hidden(),
    ])
    ->rememberColumns('users-index'); // stable, unique per table
```

Preferences are scoped by the driver to `auth()->user()`, so **one key serves
every user** — it works for any number of tables (distinct keys) and users. A
stored column that no longer exists (renamed/removed) is ignored on load.

**Where it is stored** is a driver, selected in `config('wire-table.preferences')`:

| Driver     | Persistence                                   | Setup |
|------------|-----------------------------------------------|-------|
| `null`     | Not persisted (default)                       | — |
| `session`  | The user's session                            | none |
| `database` | A `table_preferences` row per (user, table)   | publish + migrate |

```php
// config/wire-table.php
'preferences' => [
    'default' => env('WIRE_TABLE_PREFERENCES_DRIVER', 'null'), // signed-in users
    'guest'   => env('WIRE_TABLE_PREFERENCES_GUEST_DRIVER', 'session'), // visitors
    // ...
],
```

For the database driver, publish and run the migration:

```bash
php artisan vendor:publish --tag="wire-table::migrations"
php artisan migrate
```

Override the driver for a single table (e.g. force the database even when the
global default is `session`), or plug in your own store implementing
`TablePreferenceDriver`:

```php
$table
    ->rememberColumns('reports')
    ->preferenceDriver(app(DatabasePreferenceDriver::class));
```

---

## Row Context Menu

Let power users **right-click a row** to open a menu of actions at the cursor —
a shortcut alongside the actions column. The menu's actions are declared
**separately** with `rowContextMenu([...])` (they are *not* the `->actions()`
toolbar), so the menu is explicit rather than an implicit mirror of the row
buttons — pass the same action objects if you want them to match. It uses the
same menu-item styling as the action-group dropdown.

```php
$table
    ->columns([/* ... */])
    ->actions([EditAction::make()])            // the row toolbar
    ->rowContextMenu([                          // a separate right-click menu
        ViewAction::make(),
        EditAction::make(),
        DeleteAction::make(),
    ]);
```

- The menu lists exactly the **visible** menu actions (hidden/unauthorized
  actions are skipped); a row with no visible action shows no menu.
- Only **one** context menu is open at a time — right-clicking another row closes
  the previous.
- It is pinned at the pointer and clamped inside the viewport; it closes on
  outside click, `Escape`, scroll, or after choosing an action (which runs the
  action normally, e.g. opening its modal).
- Action groups are flattened into the menu.
- This is a **desktop pointer** feature — touch devices have no context menu, so
  the actions column remains the primary affordance.

---

## Notifications Per-Table

Override the global notification driver for a specific table:

```php
$table->notificationDriver('livewire')   // use Livewire events for this table
```

Useful when different parts of your app use different notification UIs.

---

## URL State Persistence

Persist table state (search, sort, per-page, filters) in the URL for bookmarkable and shareable links:

```php
public function table(Table $table): Table
{
    return $table
        ->model(User::class)
        ->queryString()
        ->columns([...])
        ->filters([...]);
}
```

URLs then look like:

```text
/users?search=john&sort=name&direction=desc&per_page=25&filter_role=admin
```

Tracked parameters:

| Parameter | State | Notes |
|---|---|---|
| `search` | global search | only when the table is searchable |
| `sort`, `direction` | sort state | only sortable column names are accepted |
| `per_page` | page size | only values from `perPageOptions()` are accepted |
| `filter_{name}` | filter value | one parameter per filter |
| `page` | current page | handled by Livewire's `WithPagination`; a page past the end re-anchors to the last populated one |

Multi-field filters expand into suffixed parameters: `NumberRangeFilter`
becomes `filter_price_min` / `filter_price_max`, a range `DateFilter`
becomes `filter_created_at_from` / `filter_created_at_to`. Filters using
`multiple()` accept array syntax (`filter_status[]=active&filter_status[]=trial`).

Incoming URL values are validated against the table configuration —
unknown sort columns, per-page values outside `perPageOptions()`, and
parameters for unknown or hidden filters are ignored. The same check runs on
the live `wire:model` path, so a crafted Livewire payload cannot ask for a page
size the table does not offer.

### Multiple Tables Per Page

Parameter names are global per URL. When two query-string-persisted tables
render on the same page, give each one a prefix:

```php
$table->queryString('orders_');   // ?orders_search=…&orders_filter_status=…
```

### Notes

- URL seeding wins over `defaultSort()` / filter `default()` values.
- Filters whose names contain dots (relationship filters such as
  `author.name`) are not URL-tracked.
- The URL updates via `history.replaceState`, so typing in the search box
  does not flood the browser history; parameters disappear again when the
  state returns to its default.

---

## Browser Testing Selectors

Every interactive part of the table carries a stable `data-testid` (plus an
accessible name/role where the control is icon-only), so [Pest v4 Browser
Testing](https://pestphp.com/docs/browser-testing) can target it at the user
level without brittle CSS.

| Part | Selector |
|------|----------|
| Search box | `data-testid="table-search"` (also `aria-label`) |
| Table filters trigger | `data-testid="table-filters-trigger"` |
| Filter reset | `data-testid="table-filter-reset"` |
| Active filter chip / remove | `data-testid="filter-chip-{name}"` / `filter-chip-remove-{name}` |
| Column picker trigger | `data-testid="table-column-toggle"` |
| Page-size selector | `data-testid="table-per-page"` |
| Pagination | `data-testid="table-page-prev"` / `table-page-next` / `table-page-{n}` |
| Sortable header | `data-testid="table-sort-{column}"` |
| Per-column filter cell | `data-testid="table-filter-{column}"` |
| Body cell | `data-testid="table-cell-{column}"` (+ `data-column`) |
| Inline-edit cell | `data-testid="table-editable-{column}"` |
| Row | `data-testid="table-row"` + `data-row-key="{key}"` (mobile card: `table-card`) |
| Select-all / row / card | `data-testid="table-select-all"` / `table-row-select` / `table-card-select` (`role="checkbox"`, `aria-label`) |
| Sub-row expand | `data-testid="table-row-expand"` (`aria-expanded`) |
| Row action | `data-testid="action-{name}"` (+ `aria-label`) |
| Header / bulk / menu action | `data-testid="header-action-{name}"` / `bulk-action-{name}` / `menu-action-{name}` |
| Bulk bar / deselect | `data-testid="table-bulk-bar"` / `table-deselect"` |
| Panel filter control | `data-testid="filter-{name}"` (the input inside a Select / Ternary / custom panel filter — distinct from the header `table-filter-{column}` cell) |
| Action group trigger | `data-testid="action-group-trigger"` |
| Copyable cell button | `data-testid="cell-copy"` |
| Button column cell | `data-testid="column-button"` |
| Polling toggle | `data-testid="polling-toggle"` |
| Sub-row controls | `data-testid="subrows-master-toggle"` / `subrows-expand-all-rows` / `subrows-reset-filters` / `subrows-show-more` / `subrows-sort-{column}` |
| Summary scope toggle | `data-testid="summary-scope-{value}"` |

Actions are also targetable by their visible label, and filter options by their
text — prefer those for the most user-faithful assertions:

```php
it('filters users by role', function () {
    $page = visit('/users');

    $page->assertSee('Ann')->assertSee('Bob');

    // Open the searchable Role filter and pick a value (user-level).
    $page->click('@table-filter-role')       // data-testid
        ->fill('search', 'Man')
        ->click('Manager');

    $page->assertSee('Bob')->assertDontSee('Ann');
});

it('edits the first row via its action', function () {
    visit('/users')
        ->within('[data-row-key="1"]', fn ($row) => $row->click('@action-edit'))
        ->assertSee('Edit user');
});
```

The whole active surface — search, sort, per-column filters, row selection, row
actions, the right-click context menu and the column picker — is reachable this
way.

**Beyond the table**, the same convention runs through the shared UI so an
end-to-end flow (open a modal, fill a form, confirm) is fully mappable:

Naming convention (so you can derive any hook): **every** form field has a
`form-field-{statePath}` container; interactive types additionally expose a
`form-{type}-{statePath}` control, whose sub-controls append `-{action|value|index}`.
Plain text / number inputs carry only the container (target it, or the `<input>`
within) — there is no `form-text-{path}` hook.

| Surface | Selector |
|---------|----------|
| Every form field (container) | `data-testid="form-field-{statePath}"` (+ `data-field`) |
| Toggle / checkbox / slider | `form-toggle-{path}`, `form-checkbox-{path}`, `form-slider-{path}` |
| Radio / checkbox-list options | `form-radio-{path}-{value}`, `form-checklist-{path}-{value}` (+ `-select-all` / `-deselect-all` / `-search`) |
| Repeater / key-value | `form-repeater-{path}-add|remove-{i}|reorder-{i}`, `form-keyvalue-{path}-add|remove-{i}` |
| File / tags | `form-file-{path}-dropzone|remove-{i}`, `form-tags-{path}-remove-{i}` |
| Date-time picker | `form-datetime-{path}-trigger|prev-month|next-month|day-{d}|hours-up|hours-down|minutes-up|minutes-down|seconds-up|seconds-down|clear|done` |
| Color / rating / OTP | `form-color-{path}` (+ `-hex` / `-swatch-{color}`), `form-rating-{path}-star-{n}`, `form-otp-{path}-{i}` |
| Editors (markdown/rich/tiptap) | `form-editor-{path}` (body) + `-{command|index}` toolbar buttons + `-write` / `-preview` tabs |
| Field / affix / hint actions | `field-action-{path}-{name}` |
| Searchable select (forms + filters) | `select-trigger` / `select-search` / `select-option-{value}` / `select-clear`; option-action triggers `form-select-{path}-create-option` / `-edit-option`; create/edit-option modals: `select-create-save|cancel`, `select-edit-save|cancel` |
| MorphToSelect | `form-select-{path}-type` (morph type) / `form-select-{path}-record` (record select) |
| Modal / slide-over / confirmation | `modal-close`, `slide-over-close`, `modal-cancel` / `modal-submit`, `modal-back` / `modal-next`, `confirmation-confirm` / `confirmation-cancel`, `modal-footer-action-{name}` |
| Wizard / tabs / section / callout | `wizard-step-{i}` / `wizard-back` / `wizard-next`, `tab-{i}`, `section-toggle`, `callout-dismiss` |
| Toasts | `toast-dismiss`, `toast-action-{i}`, `toast-expand` |
| Infolist actions | `infolist-action-{name}` |
| Sortable drag handle | `sortable-handle` (`role="button"`, `aria-label`) |

---

## Custom Views

### Custom Table View

```php
$table->view('my-custom-table-view')
```

Wire Table resolves views with namespace support. You can publish and override the default views:

```bash
php artisan vendor:publish --tag=wire-table::views
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
                    ->summarize('sum', 'Page Total', scope: 'page')
                    ->summarize('sum', 'Grand Total', scope: 'query'),

                BadgeColumn::make('status')
                    ->colors([
                        'draft' => 'gray',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'shipped' => 'success',
                        'delivered' => 'primary',
                        'cancelled' => 'danger',
                    ])
                    ->icons([
                        'pending' => 'clock',
                        'processing' => 'refresh',
                        'shipped' => 'truck',
                        'delivered' => 'check',
                        'cancelled' => 'x',
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
                    ->range()
                    ->fromLabel('From')
                    ->toLabel('Until'),

                NumberRangeFilter::make('total')
                    ->min(0)->max(1000000)->step(100),

                TernaryFilter::make('has_invoice')
                    ->label('Invoice Generated')
                    ->query(fn (Builder $q, bool $value) => $value
                        ? $q->whereNotNull('invoice_id')
                        : $q->whereNull('invoice_id')),
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
            ->paginated()
            ->perPage(25)
            ->perPageOptions([10, 25, 50, 100])
            ->selectable()
            ->striped()
            ->hoverable()
            ->stackedOnMobile()
            ->emptyState(
                heading: 'No orders found',
                description: 'Create your first order to get started.',
                icon: 'shopping-cart',
            );
    }
}
```
