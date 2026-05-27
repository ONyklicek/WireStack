---
title: Advanced Usage
order: 60
---

# Advanced Usage

## Full example

```php
// app/Livewire/TaskTable.php

use Livewire\Component;
use NyonCode\WireTable\Table;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireSortable\Concerns\WithSortable;

class TaskTable extends Component
{
    use WithTable, WithSortable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->reorderable('position')
            ->columnReorderable()
            ->selectable()
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name', 'Task')
                    ->searchable()
                    ->sortable(),
                BadgeColumn::make('status', 'Status')
                    ->sortable(),
                TextColumn::make('priority', 'Priority')
                    ->sortable(),
            ]);
    }

    protected function beforeReorder(array $items): void
    {
        $this->authorize('reorder', Task::class);
    }

    protected function afterReorder(array $items): void
    {
        cache()->forget('tasks.ordered');
        $this->dispatch('tasks-reordered');
    }

    public function render()
    {
        return view('livewire.task-table');
    }
}
```

```blade
{{-- resources/views/livewire/task-table.blade.php --}}

<div>
    <div class="mb-4 flex gap-2">
        <button wire:click="resetColumnOrder" class="btn btn-secondary">
            Reset Columns
        </button>
    </div>

    {!! $this->table !!}
</div>
```

> **Note:** The "Reorder" / "Done reordering" toggle button is rendered automatically by the sortable view. You do not need to add it manually.

## Row reordering only

```php
return $table
    ->model(Task::class)
    ->reorderable('sort_order')
    ->columns([...]);
```

## Column reordering only

```php
return $table
    ->model(Task::class)
    ->columnReorderable()
    ->columns([...]);
```

No toggle button appears. Column headers are always draggable.

## Multi-guard authentication

```php
class AdminTaskTable extends Component
{
    use WithTable, WithSortable;

    protected function getReorderableUserId(): ?int
    {
        return auth('admin')->id();
    }

    // ...
}
```

Update `config/wire-sortable.php` to match:

```php
'user_model' => 'App\\Models\\Admin',
```

## Per-component column order

By default, column order is keyed by the Eloquent model class. If you have multiple components showing the same model but want independent column orders:

```php
protected function getReorderableModelType(): ?string
{
    return static::class;
}
```

Now `TaskTable` and `TaskKanban` can have different column arrangements even though both display `Task` records.

## Programmatic reorder operations

You can use the `ReorderableColumnOrder` model directly:

```php
use NyonCode\WireSortable\Models\ReorderableColumnOrder;

// Get a user's column order for a model + table
$order = ReorderableColumnOrder::getOrder(
    userId: $user->id,
    modelType: Task::class,
    tableIdentifier: TaskTable::class,
);
// Returns: ['status', 'name', 'priority'] or null

// Save column order
ReorderableColumnOrder::saveOrder(
    userId: $user->id,
    modelType: Task::class,
    tableIdentifier: TaskTable::class,
    columnOrder: ['priority', 'status', 'name'],
);

// Delete column order (reset to default)
ReorderableColumnOrder::deleteOrder(
    userId: $user->id,
    modelType: Task::class,
    tableIdentifier: TaskTable::class,
);
```

## Sorting During Reorder Mode

When row reorder mode is enabled, the table is ordered by the configured order column so users can move records predictably. When reorder mode is disabled, the table returns to the normal search, filter, and sort behavior.

```php
SortableTable::make()
    ->model(Task::class)
    ->reorderable('sort_order');
```

Use a visible sortable handle or reorder button in your table UI so users know when they are changing persistent order rather than sorting the current view.

## Migration from v0.x

If you are upgrading from the previous version of wire-sortable:

| Before | After |
|---|---|
| `$sortableEnabled` property | `$isReordering` property |
| `toggleSortable()` | `toggleReordering()` |
| `$sortableColumnOrder` | `$reorderableColumnOrder` |
| `getSortableColumns()` | `getReorderableColumns()` |
| `dragHandleColumn()` | Removed (handles are automatic in reorder mode) |
| `dragHandleBeforeSelect()` | Removed |
| Session-based column persistence | DB-based via `reorderable_column_orders` table |
| Always-on drag handles | Toggle mode (click "Reorder" to enter) |
| Always forces sort order | Only forces sort in reorder mode |

### Steps to migrate

1. Run `php artisan wire-sortable:install` to publish the new migration
2. Run `php artisan migrate` to create the `reorderable_column_orders` table
3. Update your Livewire components:
   - Replace `use WithTable, WithSortable { ... insteadof ... }` with `use WithTable, WithSortable;`
   - Replace `toggleSortable()` calls with `toggleReordering()`
   - Replace `getSortableColumns()` calls with `getReorderableColumns()`
   - Remove `dragHandleColumn()` and `dragHandleBeforeSelect()` calls
4. Remove any manual toggle buttons -- the package now renders one automatically
