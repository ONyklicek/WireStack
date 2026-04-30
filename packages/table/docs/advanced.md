# Advanced Features

## Polling

### Table-Level Polling

Auto-refresh the entire table at regular intervals.

```php
$table->poll('5s')                           // Every 5 seconds
$table->poll('30s')                          // Every 30 seconds
$table->poll('1m')                           // Every minute
$table->polling('10s')                       // Alias for poll()
```

### Polling Options

```php
$table->poll('5s')
    ->pollKeepAlive()                        // Keep connection alive
    ->pollOnlyVisible()                      // Pause when tab is hidden
    ->pollMethod('refresh')                  // 'refresh' (soft) or 'reload' (full page)
    ->pollWhen(fn ($component) => $component->hasRunningJobs)  // Conditional
```

### Polling Directive

The table generates a `wire:poll` directive:

```php
$table->getPollingDirective();
// "wire:poll.5s.keep-alive.visible"

$table->getPollingConfig();
// ['enabled' => true, 'interval' => '5s', 'keepAlive' => true, ...]
```

### Row-Level Polling

Refresh individual rows instead of the entire table. Used with `PollColumn`:

```php
PollColumn::make('status')
    ->intervalSeconds(5)
    ->rowLevelPolling()                      // Only refresh this row
    ->stopWhen(fn (Model $record) => $record->status === 'completed')
```

### WithRowPolling Trait

For backwards compatibility, the `WithRowPolling` concern provides row-level polling support in Livewire components.

---

## Lazy Loading

Defer table rendering until after the initial page load.

```php
$table->lazy()                               // Enable lazy loading
$table->lazy()->lazyPlaceholder('Loading...') // Custom placeholder
```

The Livewire component uses a `$tableReady` property that starts as `false` and is set to `true` after mount. The table content is only rendered when `$tableReady` is `true`.

---

## Sub-Rows

Expandable row content for hierarchical data or detail views.

### Configuration

The `HasSubRows` trait on the `Table` class provides sub-row support:

```php
$table->subRows(
    view: 'partials.sub-row',               // Blade view for sub-row content
    columns: ['detail_1', 'detail_2'],       // Columns to show
)
->subRowsExpandable()                        // Enable expand/collapse
->subRowsDefaultExpanded(false)              // Start collapsed
->subRowsToggleColumn('name')               // Which column shows toggle icon
```

### Flatten Mode

```php
$table->subRowsFlattenable()                 // Allow flatten/unflatten toggle
```

When flatten mode is active, sub-rows appear as regular table rows.

### Sub-Row Filtering

```php
$table->subRowFilters([
    SelectFilter::make('type')
        ->options(['all' => 'All', 'active' => 'Active']),
])
```

### Livewire Properties

The `WithTable` trait exposes these properties for sub-rows:

```php
public array $expandedRows = [];             // Currently expanded row IDs
public bool $flattenMode = false;            // Flatten mode toggle
public array $subRowFilters = [];            // Active sub-row filters
```

---

## Column Summaries

Display aggregate values in a footer row.

```php
use NyonCode\WireTable\Columns\TextColumn;

TextColumn::make('amount')
    ->money('CZK')
    ->summary(fn (Collection $records) => $records->sum('amount'))

TextColumn::make('count')
    ->numeric()
    ->summary(fn (Collection $records) => $records->count())

TextColumn::make('avg_score')
    ->numeric(2)
    ->summary(fn (Collection $records) => round($records->avg('score'), 2))
```

The summary callback receives the full collection of current page records.

---

## Inline Editing

Three column types support inline editing out of the box:

### TextInputColumn

Full-featured text input. See [Columns](columns.md#textinputcolumn) for complete API.

```php
TextInputColumn::make('name')
    ->rules(['required', 'string', 'max:255'])
    ->saveOnBlur()
    ->saveOnEnter()
    ->liveValidation(debounce: 500)
    ->afterStateUpdated(fn (Model $record, $value) =>
        activity()->log("Name changed to {$value}")
    )
```

### ToggleColumn

Inline boolean toggle. See [Columns](columns.md#togglecolumn).

```php
ToggleColumn::make('is_active')
    ->disabled(fn (Model $record) => $record->is_locked)
```

### SelectColumn

Inline dropdown. See [Columns](columns.md#selectcolumn).

```php
SelectColumn::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
```

### Custom Save Logic

All editable columns use the `WithTable` trait's save pipeline:

1. Value is validated (if rules defined)
2. `beforeSave` formatter is applied
3. Record is updated
4. `afterStateUpdated` callback fires
5. Success/failure notification is sent

---

## Responsive Design

### Stacked Mobile Layout

```php
$table->stackedOnMobile()                   // Card layout below md breakpoint
$table->stackedOnMobile(true, 'lg')         // Card layout below lg breakpoint
```

### Column Breakpoints

```php
TextColumn::make('phone')
    ->visibleFrom('md')                      // Show on md+ screens

TextColumn::make('address')
    ->hiddenFrom('lg')                       // Hide on lg+ screens
```

Available breakpoints: `sm`, `md`, `lg`, `xl`, `2xl`

### Mobile Display Override

```php
TextColumn::make('email')
    ->mobileDisplay(fn ($value) => str()->limit($value, 20))
```

---

## Virtual Columns / Accessors

The `WithTable` trait automatically detects Laravel accessors and marks columns as virtual (non-sortable/non-searchable at the database level):

```php
// In your Model:
protected function fullName(): Attribute
{
    return Attribute::make(
        get: fn () => "{$this->first_name} {$this->last_name}",
    );
}

// In your Table:
TextColumn::make('full_name')
    ->searchable(['first_name', 'last_name'])  // Search underlying columns
```

The `configureVirtualColumns()` method in `WithTable` uses reflection to analyze accessor methods and determine which database columns they depend on.

---

## Debugging

### SQL Debugging

```php
// On the Table instance
$table->toSql();                             // Formatted SQL with bindings
$table->toRawSql();                          // ['sql' => '...', 'bindings' => [...]]
$table->dump();                              // dump() and continue
$table->dd();                                // dd() and halt

// Full debug info
$table->debug();
// Returns: model, sql, raw_sql, bindings, columns, database_columns,
//          filters, searchable, sortable, paginated, per_page,
//          default_sort, default_sort_direction
```

### Column Debugging

```php
$table->getColumnsInfo();                    // Metadata for all defined columns
$table->getDatabaseColumns();                // Schema column listing
$table->getDatabaseColumnsInfo();            // Detailed schema info (name, type)
$table->getColumnNames();                    // Simple name array
$table->dumpColumns();                       // dump() column info
$table->ddColumns();                         // dd() column info
```

### HasSqlDebug Trait

The `HasSqlDebug` trait provides a static helper for any query builder:

```php
use NyonCode\WireTable\Concerns\HasSqlDebug;

// Convert a Builder to readable SQL
$sql = HasSqlDebug::builderToSql($query);
```

---

## Color System

All components use a consistent color palette:

| Color | Aliases |
|-------|---------|
| `primary` | `blue` |
| `success` | `green` |
| `danger` | `red` |
| `warning` | `yellow` |
| `gray` | `secondary` |
| `info` | - |
| `purple` | - |
| `pink` | - |
| `orange` | - |
| `teal` | - |
| `cyan` | - |
| `indigo` | - |

Colors are applied as Tailwind CSS utility classes for buttons, badges, icons, modals, and toggle states.

---

## Icon System

Built-in SVG icons with caching for performance.

### Available Icons

`pencil`, `trash`, `eye`, `plus`, `download`, `upload`, `duplicate`, `copy`, `check`, `x`, `cog`, `mail`, `exclamation`, `warning`, `user`, `users`, `archive`, `refresh`, `key`, `shield`, `lock`, `link`, `dots-vertical`, `filter`, `chevron-down`, `calendar`, `star`, `heart`, `document`, `folder`, `check-circle`, `x-circle`, `clock`

### Rendering Icons

```php
// In any class using HasIcons trait:
$svg = $this->renderIconSvg('check', 'w-5 h-5', 'text-green-500');
$path = $this->getIconPath('pencil');
```

### Custom Icons

```php
$this->registerIcons([
    'custom' => 'M12 2L2 22h20L12 2z',       // SVG path data
]);
```

---

## Keyboard Shortcuts

Actions support keyboard shortcuts via Alpine.js:

```php
Action::make('save')->keyboardShortcut('mod+s')
Action::make('delete')->keyboardShortcut('Delete')
Action::make('new')->keyboardShortcut('ctrl+shift+n')
```

### Modifier Keys

| Modifier | Windows/Linux | macOS |
|----------|--------------|-------|
| `mod` | Ctrl | Cmd |
| `ctrl` | Ctrl | Ctrl |
| `shift` | Shift | Shift |
| `alt` | Alt | Option |
| `meta` | Win | Cmd |

### Special Keys

`Delete`, `Enter`, `Escape`, `Backspace`, `Tab`, `ArrowUp`, `ArrowDown`, `ArrowLeft`, `ArrowRight`, `F1`-`F12`

The shortcut label is auto-generated (e.g., `mod+s` becomes `Ctrl+S` or `Cmd+S`) or can be set manually:

```php
->keyboardShortcut('mod+shift+p', 'Ctrl+Shift+P')
```
