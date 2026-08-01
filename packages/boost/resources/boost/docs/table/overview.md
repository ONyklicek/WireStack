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
| `$flattenMode` | `bool\|null` | `null` | Expansion baseline (`null` = follow `subRowsDefaultExpanded()`) |

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
| `toggleAllRowExpansion()` | Master expand/collapse (`toggleFlattenMode()` is a deprecated alias) |
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

// Configure how the typed term is interpreted (see below)
->search(Closure|SearchConfig $config)
```

By default the whole term is matched as one substring against every searchable
column, OR-ed together: `LIKE '%term%'` on MySQL/MariaDB and SQLite, `ILIKE` on
PostgreSQL. The `%` and `_` a user types are escaped, so they are searched for
rather than acting as wildcards.

### Search syntax

Each capability is opted into per table — nothing is interpreted unless you ask
for it, so an existing search never changes shape underneath you.

```php
use NyonCode\WireCore\Core\Query\Search\SearchConfig;

$table->search(fn (SearchConfig $s) => $s
    ->tokenize()    // spaces mean AND, quotes keep a phrase together
    ->ranges()      // >100, <=20, 10..20, 2026-01-01..2026-03-31
    ->wildcards()   // nov* matches novak
);
```

| Capability | What the user can type | What it does |
| --- | --- | --- |
| `tokenize()` | `Ada Lovelace` | Every word must match, each across all columns — so a first name in one column and a surname in another match together. |
| `tokenize()` | `"Ada Lovelace"` | A quoted phrase stays one word and is never read as an operator. |
| `ranges()` | `>100`, `>=100`, `<10`, `<=10`, `=42` | Compares against columns that hold a number or a date. |
| `ranges()` | `10..20`, `10..`, `..20` | A closed or open-ended range. |
| `ranges()` | `2026-01-01..2026-03-31`, `31.01.2026` | The same over dates. |
| `ranges()` | `8866 01..08` | A range inside one series of a structured code — see below. |
| `wildcards()` | `nov*`, `a?b` | `*` stands for any run of characters, `?` for exactly one. |
| `literal()` | — | Switches everything back off (the default). |

A typed date is read at the granularity it was written: `2026-01-31` means that
whole day, `2026-01` that month and `2026` that year — so `<=2026-01-31` still
includes a row placed at 23:30 on the 31st.

Comparisons are only ever asked of a column that can answer them. The value type
is inferred from the model's casts (`decimal:2`, `datetime`, …); where the casts
cannot speak for a column, declare it with
[`Column::searchAs()`](columns/index.md#searching). A comparison no column can
answer — `>100` on a table of names — is searched as the literal text that was
typed rather than silently matching everything.

```php
// Only `amount` can answer ">1000"; the words narrow it further.
$table->search(fn (SearchConfig $s) => $s->tokenize()->ranges());

// User types:  praha >1000
// Rows kept:   something contains "praha"  AND  amount > 1000
```

### Ranges inside a structured code

A code such as `8866 01`, `8866 02`, … shares a series and ends in a padded
number. Declare the column with
[`searchAs('code')`](columns/index.md#searching) and the sequence can be ranged
over directly:

```php
TextColumn::make('reference')->searchable()->searchAs('code');

// User types:  8866 01..08
// SQL:         reference BETWEEN '8866 01' AND '8866 08'
```

The space inside the code is also what splits the term, so `8866 01..08`
arrives as the word `8866` and the range `01..08`. The range carries the word
directly before it, and a code column completes both bounds with it — write the
series once, not on both sides. Every other column ignores the word and reads
`01..08` as the plain range it is, so `praha 10..20` still means "contains praha,
amount between 10 and 20" on the same table. One-sided comparisons work the same
way: `8866 >=09`.

Two rules keep it honest:

- **The number must be stored padded, and typed the way it is stored.**
  Comparing as text is only correct while the width is constant (`01 … 08`
  sorts alphabetically in the same order it sorts numerically; `9 … 10` does
  not). Typing `1..8` against stored `01 … 08` finds nothing. A range typed
  across a width boundary is completed for you — `8866 50..100` is read as
  `050..100`, since a hundredth member can only exist in a three-digit series.
- **The series is the one word before the range.** `faktura 8866 01..08` ranges
  inside `8866` and requires `faktura` separately; a code containing two spaces
  is out of reach.

Search combines with filters (AND), is reset to page one when it changes, and is
persisted in the URL when [`queryString()`](advanced.md#url-state-persistence) is on.

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

`perPageOptions()` always offers the configured `perPage()`, so
`->perPage(3)` against the default options renders a select that can actually
show `3` instead of contradicting the rows on screen. A per-page value arriving
from the client that the table does not offer falls back to `perPage()`.

**Out-of-range pages re-anchor themselves.** Standard pagination clamps to the
last populated page whenever the stored page points past the end of the result
set — a shared `?page=5` link, a filter that shrank the set, rows deleted by
somebody else — so a page that no longer exists never renders as an empty
table. Simple and cursor pagination have no total to clamp against and are left
as-is.

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
->bulkMaxRecords(?int $max)                                          // rows one bulk action may load (default 1000, null = no cap)
->mobileCard(Closure $callback)                                      // name the card's title/subtitle/metric/meta

// Collapse the mobile card's row actions into one dropdown group (from N actions up)
->collapseActionsOnMobile(bool $collapse = true, int $threshold = 3)
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

#### Empty State Actions

Offer a way out of the empty state — usually "create the first record":

```php
->emptyStateActions(array $actions)
```

```php
$table
    ->emptyState(
        heading: 'No posts yet',
        description: 'Write the first one.',
        icon: 'document-text',
    )
    ->emptyStateActions([
        Action::make('create')
            ->label('Create post')
            ->url(route('posts.create')),
    ]);
```

Both `Action` and `HeaderAction` are accepted. The empty state has no rows, so
its actions run record-less — the same way header actions do, modal, form and
confirmation included:

```php
->emptyStateActions([
    Action::make('create')
        ->label('Create post')
        ->form(fn () => Form::make()->schema([
            TextInput::make('title')->required(),
        ]))
        ->action(fn (array $data) => Post::create($data)),
])
```

Two things follow from being record-less:

- Only a **static** `->url('/posts/create')` resolves. A per-record closure
  (`->url(fn ($record) => …)`) has no record here and is left unset, so the
  action renders as a plain button.
- Give an empty-state action a **name of its own**. Reusing the name (or the
  object) of a header action renders both when the table is empty — a duplicate
  `data-testid`, and if the action carries a `->keyboardShortcut()`, a window
  listener registered twice, so one keypress fires it twice.

These actions are not shown when a **filter** emptied the table: there the
records exist behind the filter, so the empty state offers to clear it instead.

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

### Fill Handle

`Table::fillHandle()` adds an Excel-style handle to editable cells: drag a value
down over the rows below and the whole range is written in one request. Opt-in,
with `Column::fillable(false)` to exclude a column. See
[Fill Handle](columns/fill-handle.md).

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
| [Selecting Rows](selection.md) | Checkboxes, select-all-matching, and the selection gestures |
| [Record Actions](record-actions.md) | Whole-row click, double-click, right-click and key bindings |
| [The Gesture Layer](gestures.md) | `gestures()` — the opt-in keyboard/drag layer, and the mobile button fallback |
| [Actions](../core/actions.md) | Full Action system — modals, forms, wizard steps, lifecycle |
