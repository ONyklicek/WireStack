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

### JavaScript assets

`wireSortable` ships as a package bundle (`dist/wire-sortable.js`) with **SortableJS compiled in**,
served by the `wire-sortable.asset` route and registered with core's `AssetManager` — so reordering
needs no npm install, no `vendor:publish` and no CDN request, and works offline and under a strict CSP.
`resources/views/partials/scripts.blade.php` is now only a thin `@@assets` wrapper (script tag + the
`.wire-sortable-*` drag CSS); the Alpine component is not in the Blade any more.

`config('wire-sortable.sortablejs_cdn')` **defaults to `null`** and can no longer affect reordering:
when set, its tag is still emitted, but the controller closes over the bundled import and never reads
`window.Sortable`. Migration note for a consuming app: if the app's *own* JavaScript relied on that CDN
script leaving a global behind, it must now set the key or bundle SortableJS itself.

As with every wireStack package, put `@@wireStackScripts` in the layout `<head>` so the bundle is in the
initial document; the view's own `@@include` remains a fallback.
