---
order: 10
---

# Wire Table

Enterprise-grade Livewire table component for Laravel. Depends on `wire-core` and `wire-forms`.

## Installation

```bash
composer require nyoncode/wire-table
```

Add to Tailwind content paths:
```js
module.exports = {
    content: [
        // ...
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    ],
}
```

Publish config (optional):
```bash
php artisan vendor:publish --tag=wire-table::config
```

---

## Quick Start

```php
use Livewire\Component;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\DeleteAction;
use NyonCode\WireCore\Actions\DeleteBulkAction;

class UserTable extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table // [tl! focus:start]
            ->model(User::class)
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Email copied!'),

                BadgeColumn::make('role')
                    ->colors([
                        'admin' => 'primary',
                        'editor' => 'success',
                        'viewer' => 'gray',
                    ]),

                TextColumn::make('created_at')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->size('sm')
                    ->textColor('gray'),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'editor' => 'Editor',
                        'viewer' => 'Viewer',
                    ]),
            ])
            ->actions([
                Action::make('edit')
                    ->icon('pencil')
                    ->url(fn (User $r) => route('users.edit', $r)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name')
            ->searchable()
            ->paginated()
            ->striped()
            ->hoverable(); // [tl! focus:end]
    }

    public function render()
    {
        return view('livewire.user-table');
    }
}
```

```blade
{{-- resources/views/livewire/user-table.blade.php --}}
<div>
    {{ $this->table }}
</div>
```

That's it. The table handles search, sort, filter, pagination, actions, and inline editing — all with zero JavaScript configuration.

---

## WithTable Trait

The `WithTable` trait is the Livewire integration layer. It provides:

- All Livewire-bound public properties (search, sort, filters, pagination, selection)
- Lifecycle hooks (`mountWithTable`, property watchers)
- Query building via `TableQueryService`
- Action execution pipeline
- Inline editing pipeline
- Modal management
- Row expansion (sub-rows)
- Column visibility toggling
- SQL/query debugging

### Public Properties (Livewire State)

These are automatically synced with the browser via Livewire:

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `$tableSearch` | `?string` | `null` | Current search term |
| `$tableSortColumn` | `string` | `''` | Current sort column name |
| `$tableSortDirection` | `string` | `'asc'` | `'asc'` or `'desc'` |
| `$tablePerPage` | `int` | `10` | Records per page |
| `$tableFilters` | `array` | `[]` | Active filter values: `['role' => 'admin', ...]` |
| `$columnFilters` | `array` | `[]` | Column-level filter values |
| `$selectedRecords` | `array` | `[]` | Primary keys of selected records |
| `$hiddenColumns` | `array` | `[]` | Column names hidden by user |
| `$expandedRows` | `array` | `[]` | Primary keys of expanded rows (sub-rows) |
| `$flattenMode` | `bool` | `false` | Show all sub-rows inline |

### Livewire Methods (wire: callable)

These are called from Alpine.js or Livewire directives in the Blade views:

| Method | Called When |
|--------|------------|
| `sortTable($column)` | User clicks column header |
| `resetSort()` | User resets sort |
| `updatedTableSearch($value)` | Search input changes |
| `updatedTableFilters()` | Filter value changes |
| `updatedColumnFilters()` | Column filter changes |
| `updatedTablePerPage()` | Per-page selector changes |
| `toggleColumnVisibility($name)` | User hides/shows column |
| `selectRecord($key)` | Checkbox toggled |
| `selectAll()` | "Select all" toggled |
| `deselectAll()` | "Deselect all" clicked |
| `expandRow($key)` | Row expand/collapse |
| `toggleFlattenMode()` | Flatten sub-rows toggle |
| `executeAction($name, $key)` | Action button clicked |
| `executeBulkAction($name)` | Bulk action clicked |
| `updateCell($column, $key, $value)` | Inline edit committed |
| `confirmActionExecution()` | Modal "confirm" clicked |
| `cancelAction()` | Modal "cancel" clicked |
| `submitActionForm()` | Action form submitted |

---

## Table Configuration API

The `Table` class provides a comprehensive fluent API. Below is the complete reference.

### Data Source

```php
// From Eloquent model class (auto-creates query)
->model(string $modelClass)

// Custom base query (overrides model)
->query(Builder $query)

// Modify the auto-generated query
->modifyQueryUsing(Closure $fn)

// Primary key column (default: 'id')
->primaryKey(string $column)
```

**Examples:**

```php
// Simple model
$table->model(User::class);

// Custom query with eager loads and scopes
$table->query(
    User::query()
        ->where('tenant_id', auth()->user()->tenant_id)
        ->withCount(['posts', 'comments'])
        ->with(['department', 'team'])
);

// Modify auto-query
$table->model(User::class)
      ->modifyQueryUsing(fn (Builder $q) => $q->where('active', true));

// UUID primary key
$table->model(Order::class)->primaryKey('uuid');
```

### Columns

```php
->columns(array $columns)
```

See [Columns Reference](columns/index.md) for all 13 column types.

### Filters

```php
->filters(array $filters)
```

See [Filters Reference](filters/index.md) for all filter types.

### Actions

```php
// Row actions (per-record)
->actions(array $actions)

// Bulk actions (for selected records)
->bulkActions(array $actions)

// Header actions (table-level, no record context)
->headerActions(array $actions)

// Actions column position
->actionsPosition(string 'start'|'end')     // default: 'end'

// Actions column alignment
->actionsAlignment(string 'left'|'center'|'right')

// Actions column header label
->actionsColumnLabel(string $label)

// Actions column fixed width
->actionsColumnWidth(string $width)          // e.g., '120px'
```

See [Actions](../core/actions.md) for the full Actions API.

### Search

```php
// Enable global search across all searchable columns
->searchable(bool $searchable = true)
```

Search uses a database-aware strategy:
- **MySQL**: `MATCH ... AGAINST` fulltext (if index exists) or `LIKE`
- **PostgreSQL**: `to_tsvector / ts_query`
- **SQLite**: `LIKE '%term%'` fallback

### Sorting

```php
// Enable column header sorting
->sortable(bool $sortable = true)

// Default sort on initial load
->defaultSort(string $column, string $direction = 'asc')
```

### Pagination

```php
// Enable pagination
->paginated(bool $paginated = true)

// Default per-page count
->perPage(int $perPage = 10)

// Per-page dropdown options
->perPageOptions(array $options = [10, 25, 50, 100])

// Simple pagination — no COUNT(*) query, just Previous/Next
->simplePagination()

// Cursor pagination — offset-free, constant-time
->cursorPagination()

// Standard pagination (default) — full page numbers
->standardPagination()
```

**When to use which:**

| Mode | Best For | Trade-offs |
|------|----------|------------|
| Standard | < 100k records, users need page numbers | COUNT(*) on every page load |
| Simple | 100k-1M records, sequential browsing | No total count, no page numbers |
| Cursor | > 1M records, real-time data | No random page access, opaque cursors |

### Selection (Bulk Actions)

```php
// Enable checkbox selection column
->selectable(bool $selectable = true)
```

When enabled, checkboxes appear. Selected record keys are stored in `tableState.selection.records` (legacy alias `$selectedRecords`). Bulk actions operate on the selection.

Selection is managed client-side (Alpine) — checking rows, select-all, and the
selection bar react instantly without a server roundtrip. The state syncs with
the next request, so bulk actions always see the current selection; tables with
a summary footer commit selection changes automatically (debounced) so
selection-scope totals stay live.

### Appearance

```php
// Alternating row colors
->striped(bool $striped = true)

// Row hover highlight (default: true)
->hoverable(bool $hoverable = true)

// Reduced cell padding
->compact(bool $compact = true)

// Table/cell borders
->bordered(bool $bordered = true)

// Custom CSS class on <table> element
->tableClass(string $class)

// Custom CSS class on <thead>
->headerClass(string $class)

// Custom CSS class on <tr>, static or computed per record
->rowClass(string|Closure $class)

// Tint the whole row with a semantic color, static or computed per record
->rowColor(string|Closure|null $color)
```

**Conditional row color.** `rowColor()` tints an entire row using the same
semantic palette as badges and every other surface (`success`, `warning`,
`danger`, `info`, `primary`, `gray`, or any raw Tailwind hue). Return `null`
from the Closure to leave a row untinted. A tinted row automatically gets a
matching same-hue hover and drops the neutral hover/zebra striping, so the
color always reads cleanly:

```php
->rowColor(fn (Invoice $record) => match ($record->status) {
    'overdue' => 'danger',
    'pending' => 'warning',
    'paid'    => 'success',
    default   => null,
})
```

Prefer `rowColor()` over hand-written background classes — it resolves through
the canonical `HasColor` owner, so it stays consistent with the rest of the UI
and works in light and dark mode. Use `rowClass()` when you need arbitrary
utilities (font weight, ring, opacity) rather than a background tint; both can
be combined on the same table:

```php
->rowColor(fn (Invoice $r) => $r->isOverdue() ? 'danger' : null)
->rowClass(fn (Invoice $r) => $r->isOverdue() ? 'font-semibold' : null)
```

### Record URL (Clickable Rows)

```php
// Make entire row clickable
->recordUrl(string|Closure $url)
```

```php
// With Closure
->recordUrl(fn (User $record) => route('users.show', $record))
```

### Responsive Layout

```php
// Stack columns vertically on mobile; 2nd arg is the breakpoint (default 'md')
->stackedOnMobile(bool $stacked = true, string $breakpoint = 'md')   // 'sm','md','lg','xl'
```

### Empty State

```php
->emptyState(?string $heading = null, ?string $description = null, ?string $icon = null)
```

```php
$table->emptyState(
    heading: 'No users found',
    description: 'Try adjusting your filters or search term.',
    icon: 'users',
)
```

### Polling (Auto-Refresh)

```php
// Enable polling at interval
->poll(string $interval = '5s')

// Continue polling when browser tab is hidden
->pollKeepAlive(bool $keepAlive = true)

// Only poll when element is visible in viewport
->pollOnlyVisible(bool $onlyVisible = true)

// Conditional polling
->pollWhen(Closure $condition)

// Livewire method to call on poll (default: re-render)
->pollMethod(string $method)
```

```php
// Poll every 5s while there are pending jobs
$table->poll('5s')
      ->pollWhen(fn () => Job::where('status', 'pending')->exists());
```

### Lazy Loading

```php
// Defer initial table render
->lazy(bool $lazy = true)

// Placeholder HTML during loading
->lazyPlaceholder(string $html)
```

```php
$table->lazy()
      ->lazyPlaceholder(
          '<div class="flex items-center justify-center p-12">
              <x-wire::icon name="refresh" class="w-8 h-8 animate-spin text-gray-400" />
          </div>'
      );
```

### Performance

```php
// Cache query results
->cacheQuery(int $ttl, ?string $key = null)

// Process records in chunks (for bulk operations)
->chunk(int $size, Closure $callback)
```

```php
// Cache for 60 seconds — key auto-generated from state hash
$table->cacheQuery(60);

// Custom cache key
$table->cacheQuery(300, 'users-table');
```

### Notifications

```php
// Override notification driver for this table
->notificationDriver(string $driver)
```

### Debugging

```php
// Get the QueryPlan object for inspection
->debugQueryPlan(): QueryPlan

// Get raw SQL with bindings interpolated
->toSql(): string

// Get column metadata analysis
->getColumnsInfo(): array
->getDatabaseColumns(): array
->getDatabaseColumnsInfo(): array
```

---

## Inline Editing

Three column types support inline editing — cells become editable inputs that validate and save immediately:

| Column Type | UI Element | Saves On |
|-------------|------------|----------|
| `TextInputColumn` | `<input>` | Blur or Enter |
| `SelectColumn` | `<select>` | Change |
| `ToggleColumn` | Switch | Click |

```php
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Columns\SelectColumn;
use NyonCode\WireTable\Columns\ToggleColumn;

$table->columns([
    TextInputColumn::make('name')
        ->rules(['required', 'string', 'max:255'])
        ->saveOnBlur(),

    SelectColumn::make('status')
        ->options([
            'draft' => 'Draft',
            'review' => 'In Review',
            'published' => 'Published',
        ])
        ->rules(['required', 'in:draft,review,published']),

    ToggleColumn::make('is_featured')
        ->onColor('success')
        ->offColor('gray')
        ->disabled(fn ($record) => ! $record->is_published),
]);
```

### Inline Edit Lifecycle

1. User modifies cell value
2. `updateCell($column, $recordKey, $newValue)` is called
3. **Validation** runs against column rules
4. **Event `CellUpdating`** dispatched (can be listened to)
5. **Eloquent update** persists the new value
6. **Event `CellUpdated`** dispatched
7. Success notification shown

If validation fails, the cell reverts and shows an error message.

### Custom Save Logic

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->editableUsing(function (Model $record, string $column, mixed $value) {
        // Custom save logic
        $record->update([$column => Str::title($value)]);
        Cache::forget("user:{$record->id}");
    })
```

---

## Real-World Patterns

### Multi-Tenant Table

```php
public function table(Table $table): Table
{
    return $table
        ->query(
            Order::query()->where('tenant_id', auth()->user()->tenant_id)
        )
        ->columns([...])
        ->filters([...]);
}
```

### Table with Complex Relations

```php
$table->model(Invoice::class)
      ->columns([
          TextColumn::make('number')->searchable(),
          TextColumn::make('client.company.name')  // nested relation
              ->label('Company')
              ->searchable(),
          TextColumn::make('items.sum.amount')      // aggregate
              ->label('Total')
              ->money('CZK'),
          TextColumn::make('payments.count')        // count aggregate
              ->label('Payments'),
          BadgeColumn::make('status')
              ->colors([...]),
      ]);
```

### Conditional Actions

```php
$table->actions([
    Action::make('approve')
        ->icon('check')
        ->color('success')
        ->visible(fn ($record) => $record->status === 'pending')
        ->action(fn ($record) => $record->approve()),

    Action::make('edit')
        ->icon('pencil')
        ->disabled(fn ($record) => $record->is_locked)
        ->url(fn ($record) => route('invoices.edit', $record)),

    ActionGroup::make('more', [
        Action::make('duplicate')
            ->icon('copy')
            ->action(fn ($r) => $r->replicate()->save()),
        Action::make('pdf')
            ->icon('document')
            ->url(fn ($r) => route('invoices.pdf', $r), openInNewTab: true),
        Action::divider(),
        Action::make('delete')
            ->icon('trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Invoice?')
            ->action(fn ($r) => $r->delete()),
    ]),
]);
```

### Dynamic Per-Page with URL Sync

All state properties are Livewire-bound, so they persist across page loads via query string (if configured in your Livewire component):

```php
class UserTable extends Component
{
    use WithTable;

    // Persist state in URL
    protected $queryString = [
        'tableSearch' => ['except' => ''],
        'tableSortColumn' => ['except' => ''],
        'tableSortDirection' => ['except' => 'asc'],
        'tablePerPage' => ['except' => 10],
    ];
}
```

---

## Related Documentation

| Document | What It Covers |
|----------|---------------|
| [Columns](columns/index.md) | All 13 column types — TextColumn, BadgeColumn, BooleanColumn, IconColumn, ImageColumn, ButtonColumn, ToggleColumn, SelectColumn, TextInputColumn, StackedColumn, SplitColumn, PollColumn |
| [Filters](filters/index.md) | SelectFilter, DateFilter, NumberRangeFilter, TernaryFilter, custom filters, column-level filters |
| [Exports](exports.md) | CSV, Excel, and PDF exports for the current table query |
| [Imports](imports.md) | CSV imports — header mapping, casting, per-row validation, updateExisting |
| [Relation Managers](relation-managers.md) | Relationship-scoped tables as standalone Livewire components |
| [Advanced](advanced.md) | Sub-rows, summary footer, polling, lazy loading, caching, debug, responsive |
| [Actions](../core/actions.md) | Full Action system — modals, forms, wizard steps, lifecycle |
