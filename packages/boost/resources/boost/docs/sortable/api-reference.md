---
title: API Reference
order: 70
---

# API Reference

## SortableTable

`NyonCode\WireSortable\SortableTable`

Extends `NyonCode\WireTable\Table`. All base Table methods remain available.

### `reorderable(?string $orderColumn = null, bool $condition = true): static`

Enable drag & drop row reordering.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$orderColumn` | `?string` | `'sort_order'` | Database column for sort position |
| `$condition` | `bool` | `true` | Conditionally enable reordering |

```php
// Default column
$table->reorderable();

// Custom column
$table->reorderable('position');

// Conditional
$table->reorderable('position', $user->can('reorder'));
```

### `alwaysReorderable(?string $orderColumn = null): static`

Keep row reordering active permanently — no toggle button is rendered. Implies `reorderable()`.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `$orderColumn` | `?string` | config default | Database column for sort position |

```php
$table->alwaysReorderable();
$table->alwaysReorderable('position');
```

### `isReorderable(): bool`

Returns whether row reordering is enabled.

### `isAlwaysReorderable(): bool`

Returns whether reordering is always active (toggle button hidden).

### `getOrderColumn(): string`

Returns the order column name.

### `paginatedWhileReordering(bool $enabled = true): static`

Keep pagination enabled while in reorder mode. By default, pagination is disabled during reordering.

### `isPaginatedWhileReordering(): bool`

Returns whether pagination is kept during reorder mode.

### `columnReorderable(bool $enabled = true): static`

Enable or disable user-specific column reordering. Column order is persisted per user + model in the database.

### `isColumnReorderable(): bool`

Returns whether column reordering is enabled.

---

## WithSortable

`NyonCode\WireSortable\Concerns\WithSortable`

Livewire trait. Use alongside `WithTable`:

```php
use WithTable, WithSortable;
```

### Properties

| Property | Type | Default | Description |
|---|---|---|---|
| `$isReordering` | `bool` | `false` | Whether the table is in row reorder mode |
| `$reorderableColumnOrder` | `array` | `[]` | Current column order (loaded from DB on mount) |

### Public methods

#### `toggleReordering(): void`

Toggle row reorder mode on/off. Clears cached records to force re-query.

No-op if the table is not reorderable.

#### `reorderRows(array $items): void`

Handle row drag & drop. Called by Alpine.js after a drag operation completes. Updates the order column in a database transaction.

Each item: `['value' => string|int, 'order' => int]`

`order` is the row's new position on screen, not the value written. The dragged rows keep the set of order values they already held, redistributed in the new visual sequence, so a drag over a searched, filtered or paginated subset cannot move the rows it does not show — see [Reordering a narrowed list](row-sorting.md#reordering-a-narrowed-list). The positions are written verbatim only when the order column is null or constant and has nothing to redistribute.

No-op if:
- The table is not reorderable
- The table is not in reorder mode (`$isReordering === false`)
- A row's key falls outside the table's base query (it is dropped from the write)

#### `reorderColumns(array $columnOrder): void`

Handle column drag & drop. Validates column names against the table definition and persists to the `reorderable_column_orders` table.

No-op if:
- The table is not column-reorderable
- The user is not authenticated (`getReorderableUserId()` returns `null`)
- No valid column names are provided

#### `resetColumnOrder(): void`

Resets column order to the default (as defined in `table()`) and deletes the database entry.

#### `getReorderableColumns(): array`

Returns columns in the user's saved order. Newly added columns (not present in the saved order) are appended at the end. Removed columns (in saved order but no longer in the table definition) are silently skipped.

#### `isTableReordering(): bool`

Returns whether the table is currently in row reorder mode. Alias for `$this->isReordering`.

### Protected overrides

These methods override `WithTable`'s protected factory/hook methods. No `insteadof` clause is needed — PHP resolves them automatically because `WithSortable` is listed after `WithTable`.

#### `getTableView(): string`

Returns `'wire-sortable::tables.index'` when row or column reordering is enabled. Falls through to `'wire-table::tables.index'` otherwise.

#### `interceptTableRecords(): LengthAwarePaginator|Paginator|CursorPaginator|Collection|null`

In reorder mode (without `paginatedWhileReordering`): bypasses pagination and the column sort, but keeps search and filters applied. Returns every matching record ordered by the sort column ascending.

Otherwise: returns `null` to let `WithTable` handle record fetching normally.

### Protected hooks

#### `beforeReorder(array $items): void`

Called before the database update. Override for authorization or pre-processing.

```php
protected function beforeReorder(array $items): void
{
    $this->authorize('reorder', Task::class);
}
```

#### `afterReorder(array $items): void`

Called after the database update. Override for cache invalidation or events.

```php
protected function afterReorder(array $items): void
{
    Cache::forget('tasks.ordered');
    $this->dispatch('tasks-reordered');
}
```

#### `getReorderableUserId(): ?int`

Returns the user ID for column order persistence. Defaults to `auth()->id()`.

Override for custom auth guards:

```php
protected function getReorderableUserId(): ?int
{
    return auth('admin')->id();
}
```

#### `getReorderableModelType(): ?string`

Returns the model type key for column order persistence. Defaults to the Eloquent model class name (e.g., `App\Models\Task`).

Override to use a custom key:

```php
protected function getReorderableModelType(): ?string
{
    return static::class; // component class instead of model
}
```

---

## ReorderableColumnOrder

`NyonCode\WireSortable\Models\ReorderableColumnOrder`

Eloquent model for the `reorderable_column_orders` table.

### Properties

| Property | Type | Description |
|---|---|---|
| `$user_id` | `int` | Foreign key to the users table |
| `$model_type` | `string` | Eloquent model class name |
| `$column_order` | `array` | JSON-cast array of column names |

### Relationships

#### `user(): BelongsTo`

Belongs to the user model configured in `wire-sortable.user_model`.

### Static methods

#### `getOrder(int $userId, string $modelType, string $tableIdentifier): ?array`

Get the saved column order for a user + model + table combination. Returns `null` if no record exists.

#### `saveOrder(int $userId, string $modelType, string $tableIdentifier, array $columnOrder): void`

Create or update the column order for a user + model + table combination (upsert).

#### `deleteOrder(int $userId, string $modelType, string $tableIdentifier): void`

Delete the column order record for a user + model + table combination.

## Configuration

`config/wire-sortable.php`

| Key | Type | Default | Description |
|---|---|---|---|
| `order_column` | `string` | `'sort_order'` | Default order column name |
| `sortablejs_cdn` | `?string` | `null` | Optional extra SortableJS `<script>`. SortableJS is compiled into the package bundle, so this never affects reordering — set it only if your own code needs a global `window.Sortable` |
| `animation` | `int` | `150` | Drag animation duration in milliseconds |
| `user_model` | `string` | `'App\\Models\\User'` | User model class for column order relationships |
| `user_key_type` | `string` | `'id'` | Primary key type of the user model, used by the migration to type the `user_id` column. Use `'uuid'` or `'ulid'` for non-integer auth keys |

---

## Alpine.js component

`wireSortable(config)` is registered globally via `Alpine.data()` by the package
bundle (`dist/wire-sortable.js`, SortableJS compiled in). It registers as soon as the
bundle runs, not only from `alpine:init`, so it works when the bundle arrives after
Alpine has already started — a `wire:navigate` visit, a lazily rendered table, a modal.

### Config options

| Option | Type | Default | Description |
|---|---|---|---|
| `rowReorderable` | `bool` | `false` | Enable row reordering |
| `columnReorderable` | `bool` | `false` | Enable column reordering |
| `isReordering` | `bool` (entangled) | `false` | Livewire-synced reorder mode state |
| `orderColumn` | `string` | `'sort_order'` | Order column name |
| `animation` | `int` | `150` | SortableJS animation duration (ms) |

### Behavior

- `isReordering` is entangled with the Livewire `$isReordering` property via `@entangle`
- When `isReordering` changes, the component automatically initializes or destroys SortableJS on the `<tbody>`
- Drag handles are dynamically added/removed from the DOM
- Column sorting is always active when `columnReorderable` is `true` (independent of reorder mode)
- After Livewire updates the table, SortableJS is re-initialized

---

## Translations

`lang/{locale}/messages.php`

| Key | EN | CS | Description |
|---|---|---|---|
| `reorder` | Reorder | Přeuspořádat | Toggle button label (inactive) |
| `done_reordering` | Done reordering | Hotovo | Toggle button label (active) |
