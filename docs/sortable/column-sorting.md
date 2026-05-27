---
title: Column Reordering
order: 40
---

# Column Reordering

Drag & drop column header reordering with per-user, per-table database persistence.

## Basic usage

```php
use Livewire\Component;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireSortable\Concerns\WithSortable;

class TaskTable extends Component
{
    use WithTable, WithSortable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->columnReorderable()
            ->columns([
                TextColumn::make('name', 'Name'),
                TextColumn::make('status', 'Status'),
                TextColumn::make('priority', 'Priority'),
            ]);
    }
}
```

Users can drag column headers to rearrange them. The body cells reorder automatically to match.

## Database persistence

Column order is stored in the `reorderable_column_orders` table with a unique constraint on `(user_id, model_type, table_identifier)`. This means:

- Each user has their own column arrangement
- The arrangement is tied to both the Eloquent model class and the Livewire component class
- Multiple table components showing the same model get **independent** column orders
- When a user is deleted, their column orders are cascade-deleted

### Storage structure

```
reorderable_column_orders
├── user_id: 1
│   ├── model_type: "App\Models\Task", table_identifier: "App\Livewire\TaskListTable"
│   │   → ["status", "name", "priority"]
│   ├── model_type: "App\Models\Task", table_identifier: "App\Livewire\TaskBoardTable"
│   │   → ["priority", "name", "status"]
│   └── model_type: "App\Models\User", table_identifier: "App\Livewire\UserTable"
│       → ["email", "name", "role"]
├── user_id: 2
│   └── model_type: "App\Models\Task", table_identifier: "App\Livewire\TaskListTable"
│       → ["priority", "status", "name"]
```

### How it loads

On component mount (`mountWithSortable`), the trait:

1. Gets the current user ID via `getReorderableUserId()` (defaults to `auth()->id()`)
2. Gets the model type via `getReorderableModelType()` (the Eloquent model class)
3. Gets the table identifier via `getReorderableTableIdentifier()` (the Livewire component class)
4. Queries `ReorderableColumnOrder::getOrder($userId, $modelType, $tableIdentifier)`
5. If found, sets `$reorderableColumnOrder` with the saved column names

### How it saves

When the user drags a column header:

1. Alpine.js reads the new header order from `th[data-sortable-column]` attributes
2. Calls `$wire.reorderColumns(['status', 'name', 'priority'])`
3. The trait validates column names against the table definition (ignores unknown columns)
4. Saves via `ReorderableColumnOrder::saveOrder($userId, $modelType, $tableIdentifier, $columnOrder)`

### Column name validation

The trait filters incoming column names against the table definition. Only names that match a defined column are persisted. This prevents:

- Injection of arbitrary column names from the frontend
- Stale column names from breaking the table after a column is removed from the definition

When loading saved order, columns that no longer exist in the definition are silently skipped. Newly added columns (not present in the saved order) are appended at the end.

## Getting ordered columns

Use `getReorderableColumns()` to get columns in the user's preferred order:

```php
$columns = $this->getReorderableColumns();
```

This returns all defined columns sorted by the saved order. Columns not present in the saved order are appended at the end.

## Resetting column order

Call `resetColumnOrder()` to restore the default order:

```blade
<button wire:click="resetColumnOrder">
    Reset Column Order
</button>
```

This clears both the component property and the database entry.

## Combining with row reordering

Row and column reordering work independently and can be used together:

```php
return $table
    ->model(Task::class)
    ->reorderable('position')
    ->columnReorderable()
    ->columns([...]);
```

## Multiple tables over the same model

By default, the table identifier is the Livewire component class (`static::class`). This means two components like `TaskListTable` and `TaskBoardTable` that both query `App\Models\Task` will have independent column orders without any extra configuration.

If you need a custom identifier (e.g., a single component that renders different table configurations), override `getReorderableTableIdentifier()`:

```php
protected function getReorderableTableIdentifier(): string
{
    return static::class . ':' . $this->tableVariant;
}
```

## Custom user resolution

By default, the trait uses `auth()->id()` to identify the user. Override `getReorderableUserId()` for custom logic:

```php
// Multi-guard authentication
protected function getReorderableUserId(): ?int
{
    return auth('admin')->id();
}
```

## Custom model type

By default, the model type is resolved from `$table->getQuery()->getModel()`. Override `getReorderableModelType()` if you need a custom key:

```php
protected function getReorderableModelType(): ?string
{
    return 'custom-key';
}
```

## Guests (unauthenticated users)

When `getReorderableUserId()` returns `null`, column reordering is silently disabled:

- `mountWithSortable()` skips loading saved order
- `reorderColumns()` is a no-op
- `resetColumnOrder()` clears only the local property

The drag & drop UI still works in the browser (via Alpine.js), but changes are not persisted.

## Column Reordering Flow

1. The Alpine component calls `initColumnSortable()` on the first `<thead tr>`
2. Header cells are identified by `wire:click="sortTable('column')"` or `data-column` attributes
3. Each identified cell gets a `data-sortable-column` attribute and a `grab` cursor
4. SortableJS enables horizontal dragging of `th[data-sortable-column]` elements
5. On drag end, body `<td>` cells are reordered to match the new header order
6. The new column name sequence is sent to `reorderColumns()` via Livewire
7. The trait validates column names, saves to `reorderable_column_orders`, and updates the local property
8. Non-data columns (selection, actions, drag handle) are excluded from dragging
