# Wire Sortable

Drag and drop row and column reordering for Wire Table.

## Requirements

- PHP 8.2+
- Laravel 10, 11, or 12
- Livewire 3.x
- Tailwind CSS 3.x
- `nyoncode/wire-table`

## Installation

```bash
composer require nyoncode/wire-sortable
```

Service providers register automatically through Laravel package discovery.

Publish the config file when you need to change the order column, SortableJS loading, animation, or user model:

```bash
php artisan vendor:publish --tag=wire-sortable-config
```

Run the package migration when you use persistent per-user column ordering:

```bash
php artisan vendor:publish --tag=wire-sortable-migrations
php artisan migrate
```

## Tailwind CSS

Add the package views to your Tailwind content paths.

```js
export default {
    content: [
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-sortable/resources/views/**/*.blade.php',
    ],
}
```

## Quick Start

```php
use Livewire\Component;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\TextColumn;
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
            ->columns([
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('status')->sortable(),
            ]);
    }
}
```

```blade
<div>
    {{ $this->table }}
</div>
```

## Configuration

```php
return [
    'order_column' => 'sort_order',
    'sortablejs_cdn' => 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
    'animation' => 150,
    'user_model' => 'App\\Models\\User',
];
```

Set `sortablejs_cdn` to `null` when your application bundles SortableJS itself.

## Documentation

| Document | Description |
|----------|-------------|
| [Sortable Overview](../../docs/sortable/overview.md) | Row and column reordering |
| [Installation](../../docs/sortable/installation.md) | Setup and frontend requirements |
| [Row Reordering](../../docs/sortable/row-sorting.md) | Persistent row order |
| [Column Reordering](../../docs/sortable/column-sorting.md) | Per-user column order |
| [API Reference](../../docs/sortable/api-reference.md) | Sortable table and trait API |
| [Configuration](../../docs/configuration.md) | Package config reference |

## License

MIT
