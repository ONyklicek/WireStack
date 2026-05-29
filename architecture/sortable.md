# Sortable Package

Owner package: `packages/sortable`

## What It Owns

`wire-sortable` extends `wire-table` with:

- row reorderability
- optional column reorderability
- plugin registration
- table macros
- persistence for reordered column state

It is not standalone. It depends on `wire-table`.

## First Files To Read

- `packages/sortable/src/WireSortableServiceProvider.php`
- `packages/sortable/src/SortablePlugin.php`
- `packages/sortable/src/SortableTable.php`
- `packages/sortable/src/Concerns/WithSortable.php`
- `packages/sortable/src/Models/ReorderableColumnOrder.php`

Also read:

- `architecture/table.md`
- `architecture/integrations.md`

## Provider Responsibilities

`WireSortableServiceProvider` does two important things:

1. Registers `SortablePlugin` into core `PluginManager`
2. Adds `Table::macro()` methods such as:
   - `reorderable()`
   - `alwaysReorderable()`
   - `paginatedWhileReordering()`
   - `columnReorderable()`

If sorting features are missing entirely, start with provider boot/registration before debugging table behavior.

## Main Areas

### `SortablePlugin.php`

Plugin-level integration with the shared plugin system.

### `WithSortable.php`

Trait-level sortable behavior.

### `SortableTable.php`

Focused sortable support surface for table consumers.

### `Models/ReorderableColumnOrder.php`

Persistence for saved column order preferences.

### `resources/views/`

Sortable UI fragments and scripts.

## Typical Changes

- sortable feature wiring:
  `WireSortableServiceProvider.php`
- plugin behavior:
  `SortablePlugin.php`
- table runtime interaction:
  `WithSortable.php` plus relevant table files
- persisted order bugs:
  `ReorderableColumnOrder.php`
- drag-and-drop UI:
  sortable views/scripts plus table views if the integration point changed

## Tests To Run

Start with:

- `composer test:sortable`

Usually also run:

- `composer test:table`

Add integration tests if plugin boot or state flow changed:

- `vendor/bin/pest --configuration phpunit.xml --testsuite "Integration"`

Useful authored docs:

- `docs/sortable/overview.md`
- `docs/sortable/installation.md`
- `docs/sortable/advanced.md`
- `docs/sortable/api-reference.md`
