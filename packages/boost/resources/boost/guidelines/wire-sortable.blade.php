## wire-sortable

Adds drag & drop reordering to wire-table via a plugin and `Table` macros. Publish the config and the
`reorderable_column_orders` migration with `php artisan wire-sortable:install`.

### Row reordering

    public function table(Table $table): Table
    {
        return $table
            ->query(Task::query())
            ->reorderable('sort_order')      // enable drag handle, persist to this column
            ->columns([
                TextColumn::make('title'),
            ]);
    }

- `->reorderable($column = null, $condition = true)` enables reordering; `->alwaysReorderable()` keeps it on.
- `->paginatedWhileReordering()` allows reordering across pages.
- `->columnReorderable()` enables column (header) reordering.
- The order column defaults to `wire-sortable.order_column` (`sort_order`).
- The drag handle markup is owned by `Table::getDragHandleHtml()` (a Blade partial), not hand-built JS.

These are `Table` macros registered by the sortable service provider, so they are only available when
wire-sortable is installed.
