---
title: Row Reordering
order: 30
---

# Row Reordering

Drag & drop row reordering with a toggle mode and automatic database persistence.

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
            ->reorderable()
            ->columns([
                TextColumn::make('name', 'Name'),
                TextColumn::make('status', 'Status'),
            ]);
    }

    public function render()
    {
        return view('livewire.task-table');
    }
}
```

The `WithSortable` trait registers Table macros that add `reorderable()` and other methods to the base `Table` class. You chain them directly on `$table`.

The Blade template uses the computed `$table` property:

```blade
{{-- resources/views/livewire/task-table.blade.php --}}
<div>
    {!! $this->table !!}
</div>
```

## How reorder mode works

1. A **"Reorder" button** appears in the table toolbar
2. User clicks the button to **enter reorder mode**
3. In reorder mode:
   - Drag handles appear on each row
   - Pagination is disabled (all records are shown)
   - Sorting, search, and filters are bypassed
   - Rows are ordered by the sort column ascending
4. User drags rows to their desired position
5. On drag end, the new order is saved to the database
6. User clicks **"Done reordering"** to exit reorder mode
7. The table returns to its normal state with pagination, sorting, and filters restored

## Custom order column

```php
return $table
    ->model(Task::class)
    ->reorderable('position')
    ->columns([...]);
```

The column name must exist in your database table. Defaults to `sort_order`.

## Always-on reorder mode

If you want drag handles visible at all times without a toggle button:

```php
return $table
    ->model(Task::class)
    ->alwaysReorderable()
    ->columns([...]);
```

With a custom column:

```php
return $table
    ->model(Task::class)
    ->alwaysReorderable('position')
    ->columns([...]);
```

In this mode the table is always in reorder mode -- no toggle button is rendered and `$isReordering` is set to `true` on mount.

## Conditional reordering

Disable reordering based on a condition (e.g., user permissions):

```php
return $table
    ->model(Task::class)
    ->reorderable('sort_order', auth()->user()->can('reorder', Task::class))
    ->columns([...]);
```

When `false` is passed as the second argument, the reorder button does not appear and the `toggleReordering()` method is a no-op.

## Paginated while reordering

By default, pagination is disabled in reorder mode so the user can drag across the full dataset. If you have a large dataset and prefer to keep pagination:

```php
return $table
    ->model(Task::class)
    ->reorderable()
    ->paginatedWhileReordering()
    ->columns([...]);
```

> **Note:** With pagination enabled, users can only reorder within the current page.

## Lifecycle hooks

Override these methods in your component to hook into the reorder process:

```php
protected function beforeReorder(array $items): void
{
    // Authorize, validate, or dispatch pre-reorder logic
    $this->authorize('reorder', Task::class);
}

protected function afterReorder(array $items): void
{
    // Clear cache, dispatch events, log activity
    Cache::forget('tasks.ordered');
    $this->dispatch('tasks-reordered');
}
```

Each `$items` entry is an associative array:

```php
[
    ['value' => '1', 'order' => 1],
    ['value' => '5', 'order' => 2],
    ['value' => '3', 'order' => 3],
]
```

- `value` -- the record's primary key
- `order` -- the new 1-based position

## Custom primary key

By default, reorder queries use the table's primary key (`id`). If your model uses a different key:

```php
return $table
    ->model(Task::class)
    ->primaryKey('uuid')
    ->reorderable()
    ->columns([...]);
```

## Row Reordering Flow

1. The table toolbar shows a reorder toggle when row reordering is enabled
2. The user enters reorder mode
3. The table displays records ordered by the configured order column
4. The user drags rows into the desired order
5. Wire Sortable receives the new order and updates the order column in one database transaction
6. Your `beforeReorder()` and `afterReorder()` hooks run around the save
7. The table refreshes and exits or stays in reorder mode based on the user's action
