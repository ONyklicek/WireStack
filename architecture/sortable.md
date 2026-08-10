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

Two methods there decide together what reorder mode is allowed to be, and neither
reads right without the other:

- `interceptTableRecords()` takes over the fetch in row-reorder mode. It drops
  pagination and the user's column sort — the sequence on screen is the sequence
  a drop writes back, so it can only be the order column's — but it goes through
  `WithTable::buildTableQuery()`, so **search and filters stay applied**. They
  used to be dropped too, which left `alwaysReorderable()` tables rendering a
  search box that could never do anything, since those never leave reorder mode.
- `reorderRows()` is why that is safe. The client reports each row's new position
  as `1..n`, and writing those positions renumbers the visible subset over the
  top of every row it cannot see — a filtered drag, or any drag under
  `paginatedWhileReordering()`, would move rows on other pages. Instead
  `resolveReorderSlots()` collects the order values the dragged rows already
  hold, sorts them ascending, and redistributes them in the new visual order.
  Rows outside the drag keep their slots; gaps in the column survive.

The lookup runs through `$table->getQuery()`, which is also what keeps a
client-supplied key outside the scoped set out of the write (see
`tests/Feature/ReorderScopeTest.php`). Behaviour is asserted by
`tests/Feature/ReorderSearchTest.php`.

### `SortableTable.php`

Focused sortable support surface for table consumers.

### `Models/ReorderableColumnOrder.php`

Persistence for saved column order preferences.

### `resources/views/`

Sortable UI fragments and scripts.

### `resources/js/sortable.js` — the drag controller and its morph guards

One Alpine component wraps the whole table whenever `reorderable()` **or**
`columnReorderable()` is on, and it registers two global Livewire morph hooks.
They are the highest-blast-radius code in the package: a morph hook that says
"skip" decides whether a Livewire response is applied at all, for the entire
table, whatever the render was about.

Two rules when touching them:

1. **`skip()` takes the whole subtree of `el` with it, and `contains()` is
   inclusive.** A guard evaluated at the wrapper therefore skips the table. Any
   condition must name the exact node it protects (`el === cell`), never "the
   focused element is somewhere below this one".
2. **"An input inside the table" is not the same as "a cell being edited".** The
   search box, the filter inputs and the per-page select are inputs inside the
   table, and each of them exists to *cause* the morph. The cell being edited is
   identified by `[data-record-key][data-column-name]` — the same pair
   `wireTableLive.busy()` reads, and a cross-package contract asserted by
   `packages/sortable/tests/Feature/MorphGuardTest.php`.

3. **The hooks are installed once per document, not once per controller.**
   `Livewire.hook()` has no off switch, so registering from `init()` stacks a
   fresh pair on every re-init — a second reorderable table, a `wire:navigate`,
   a table in a lazily loaded modal. Live controllers live in a module-level
   `Map` keyed by their wrapper element, added in `init()` and removed in
   `destroy()`. Keyed by the element deliberately: Alpine calls `destroy()` with
   a merge proxy of the scope, **not** the instance `init()` saw, so
   `delete(this)` silently deletes nothing — and an element key also lets a
   replacement take over the entry rather than join it. Same shape as
   `packages/table/resources/js/record-actions.js` and
   `packages/core/resources/js/fill/controller.js`.

The drag guard is the one case that legitimately skips everything: mid-drag the
DOM holds rows the server render knows nothing about.

`morph.updated` fires once per patched element, so the re-init it schedules is
coalesced (`scheduleSetup()`) — one `setup()` per morph, not one per node.

The bundle is committed: any change here needs `npm run build:sortable-assets`,
which `SortableAssetTest` and `MorphGuardTest` will fail without.

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

Anything in `resources/js/sortable.js` is browser-only and Pest cannot see it —
rebuild the bundle and run the drivers:

- `npm run build:sortable-assets`
- `npm run verify:drivers -- sortable-morph` — the morph guards
- `npm run verify:drivers -- column-reorder` — the header drag and the body mirror
- `npm run verify:drivers -- sortable-everything` — a real drop over a narrowed
  list: what `onEnd` reads out of the DOM, and what the slot redistribution in
  `reorderRows()` does with it. The only check that a drag on page two, or over
  two search matches, leaves the rows it cannot see alone. It **writes to the
  workbench database** and restores the seeded order on the way out — the other
  sortable fixtures read the same six tasks.

Useful authored docs:

- `docs/sortable/overview.md`
- `docs/sortable/installation.md`
- `docs/sortable/advanced.md`
- `docs/sortable/api-reference.md`
