---
title: Introduction
order: 10
---

# Wire Sortable

Reorderable rows and columns for [wire-table](../table/overview.md). Row reordering persists to a database column. Column reordering persists per user, per model, and per table component to the database.

Built on [SortableJS](https://sortablejs.github.io/Sortable/) and [Alpine.js](https://alpinejs.dev/).

## Features

- **Row reordering** -- toggle button switches the table into reorder mode; drag rows to change their position, persisted to a database column
- **Always-on reorder mode** -- optionally skip the toggle and keep drag handles visible at all times
- **Column reordering** -- drag column headers to rearrange; order is stored per user, per model, and per table component in the database
- **Reorder mode** -- in reorder mode, pagination, sorting, search and filters are disabled so the user can drag freely across the full dataset
- **Paginated while reordering** -- optionally keep pagination enabled during reorder mode
- **Lifecycle hooks** -- `beforeReorder()` / `afterReorder()` for authorization, caching, events
- **Multi-table support** -- multiple table components over the same model get independent column orders
- **Dark mode** -- all drag indicators support light and dark themes
- **Livewire 3 compatible** -- survives morphs, pagination, and filter changes

## Typical Setup

Add `WithSortable` beside `WithTable`, then enable row or column reordering on the table.

```php
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class TaskTable extends Component
{
    use WithTable, WithSortable;

    public function table(Table $table): Table
    {
        return $table
            ->model(Task::class)
            ->reorderable('sort_order')
            ->columnReorderable()
            ->columns([
                // ...
            ]);
    }
}
```

## Requirements

| Dependency | Version |
|------------|---------|
| PHP | ^8.2 |
| Laravel | ^10.0 / ^11.0 / ^12.0 / ^13.0 |
| Livewire | ^3.0 |
| wire-core | ^0.1 |
| wire-table | ^0.1 |
| Tailwind CSS | ^3.0 / ^4.0 |

## Pages

| Page | Description |
|------|-------------|
| [Installation](installation.md) | Composer, migrations, SortableJS, Tailwind |
| [Row Reordering](row-sorting.md) | Toggle mode, drag & drop, lifecycle hooks |
| [Column Reordering](column-sorting.md) | Per-user column ordering, DB persistence |
| [Customization](customization.md) | CSS classes, dark mode, view publishing |
| [Advanced Usage](advanced.md) | Full example, configuration details, and troubleshooting |
| [API Reference](api-reference.md) | SortableTable, WithSortable, ReorderableColumnOrder, config |
