<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Concerns;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use NyonCode\WireSortable\Models\ReorderableColumnOrder;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Table;

/**
 * Livewire trait for reorderable tables.
 *
 * Row reorder: Filament-style toggle mode with drag & drop.
 * Column reorder: per-user column ordering persisted in DB.
 *
 * Just add the trait alongside WithTable:
 *
 *   class TaskTable extends Component {
 *       use WithTable, WithSortable;
 *   }
 */
trait WithSortable
{
    public bool $isReordering = false;

    /**
     * Current column order (loaded from DB on mount).
     *
     * @var array<int, string>
     */
    public array $reorderableColumnOrder = [];

    // ==========================================
    // WithTable hooks
    // ==========================================

    protected function getTableView(): string
    {
        $table = $this->getTable();

        if ($table->isReorderable() || $table->isColumnReorderable()) {
            return 'wire-sortable::tables.index';
        }

        return 'wire-table::tables.index';
    }

    /**
     * Provide toolbar widget for the reorder toggle icon.
     *
     * @return array<int, string>
     */
    public function getTableToolbarWidgets(): array
    {
        $table = $this->getTable();

        if (! $table->isReorderable() || $table->isAlwaysReorderable()) {
            return [];
        }

        $title = $this->isReordering
            ? __('wire-sortable::messages.done_reordering')
            : __('wire-sortable::messages.reorder');

        $activeClass = $this->isReordering
            ? 'bg-primary-100 text-primary-600 dark:bg-primary-900/30 dark:text-primary-400'
            : 'text-gray-400 hover:text-gray-600 hover:bg-gray-100 dark:text-gray-500 dark:hover:text-gray-300 dark:hover:bg-gray-700';

        $icon = $this->isReordering
            ? '<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />'
            : '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />';

        return [
            '<button type="button" wire:click="toggleReordering" class="p-1.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 '.$activeClass.'" title="'.e($title).'">
                <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">'.$icon.'</svg>
            </button>',
        ];
    }

    // ==========================================
    // Lifecycle
    // ==========================================

    public function mountWithSortable(): void
    {
        $table = $this->getTable();

        if ($table->isAlwaysReorderable()) {
            $this->isReordering = true;
        }

        if (! $table->isColumnReorderable()) {
            return;
        }

        $userId = $this->getReorderableUserId();
        $modelType = $this->getReorderableModelType();
        $tableIdentifier = $this->getReorderableTableIdentifier();

        if ($userId === null || $modelType === null) {
            return;
        }

        $saved = ReorderableColumnOrder::getOrder($userId, $modelType, $tableIdentifier);

        if (is_array($saved) && ! empty($saved)) {
            $this->reorderableColumnOrder = $saved;
        }
    }

    // ==========================================
    // WithTable hook: interceptTableRecords
    // ==========================================

    /**
     * Intercept record fetching in reorder mode.
     *
     * Called by WithTable via method_exists. Reorder mode drops pagination and
     * the user's sort — a drag needs the list whole and in its canonical order —
     * but it keeps search and filters applied. A narrowed list is safe to drag
     * because {@see reorderRows()} redistributes the slots the dragged rows
     * already own rather than renumbering the table from 1; and a table left
     * permanently in reorder mode by `alwaysReorderable()` would otherwise
     * render a search box that can never do anything.
     */
    protected function interceptTableRecords(): LengthAwarePaginator|Paginator|CursorPaginator|Collection|null
    {
        $table = $this->getTable();

        if (
            $this->isReordering
            && $table->isReorderable()
            && ! $table->isPaginatedWhileReordering()
        ) {
            // WithTable owns search, filters and the query cache; fall back to the
            // bare table query for a host that composes this trait on its own.
            $query = method_exists($this, 'buildTableQuery')
                ? $this->buildTableQuery()
                : $table->getQuery();

            // reorder() rather than orderBy(): the sort the user picked from a
            // header, and any grouping order, have to give way to the drag order.
            return $query->reorder($table->getOrderColumn(), 'asc')->get();
        }

        return null;
    }

    // ==========================================
    // Row Reorder
    // ==========================================

    /**
     * Toggle row reorder mode on/off.
     */
    public function toggleReordering(): void
    {
        $table = $this->getTable();

        if (! $table->isReorderable()) {
            return;
        }

        $this->isReordering = ! $this->isReordering;
        $this->cachedRecords = null;
    }

    /**
     * Handle row drag & drop. Updates order column in DB.
     *
     * @param  array<int, array{value: string|int, order: int}>  $items
     */
    public function reorderRows(array $items): void
    {
        $table = $this->getTable();

        if (! $table->isReorderable() || ! $this->isReordering) {
            return;
        }

        // Authorization seam (defense-in-depth on top of the base-query scope below):
        // override canReorder() to enforce a policy/Gate check on the reorder action.
        if (! $this->canReorder($items)) {
            return;
        }

        $orderColumn = $table->getOrderColumn();
        $primaryKey = $table->getPrimaryKey();

        $this->beforeReorder($items);

        $slots = $this->resolveReorderSlots($items, $table, $orderColumn, $primaryKey);

        DB::transaction(function () use ($slots, $orderColumn, $primaryKey, $table) {
            foreach ($slots as $key => $order) {
                // Scope every write through the table's base query so a developer
                // `->query(Task::where('team_id', $tid))` / modifyQueryCallback applies:
                // a client-supplied primary key outside the visible/scoped set matches
                // NOTHING and is never written. Previously this used a model-global
                // `$modelClass::where(...)` that rewrote the order column of ANY row of
                // the model by client key — an IDOR write.
                (clone $table->getQuery())
                    ->where($primaryKey, $key)
                    ->update([$orderColumn => $order]);
            }
        });

        $this->afterReorder($items);

        $this->cachedRecords = null;
    }

    /**
     * Work out the order value each dragged row should end up with.
     *
     * The dragged rows keep the set of slots they already occupied: their current
     * order values, sorted ascending, handed back out in the new visual sequence.
     * Rows that were not part of the drag — hidden by a search, by a filter, or
     * sitting on another page — therefore keep the positions they had. That is
     * what makes dragging a narrowed list safe: writing the client's own `1..n`
     * positions instead would renumber the visible subset straight over the top
     * of every row it cannot see.
     *
     * The lookup runs through the table's base query, so a key outside the scoped
     * set brings no slot back with it and drops out of the write entirely.
     *
     * @param  array<int, array{value: string|int, order: int}>  $items
     * @return array<array-key, mixed> primary key => order value
     */
    private function resolveReorderSlots(array $items, Table $table, string $orderColumn, string $primaryKey): array
    {
        $keys = [];

        foreach ($items as $item) {
            if (isset($item['value']) && $item['value'] !== '') {
                $keys[] = $item['value'];
            }
        }

        if ($keys === []) {
            return [];
        }

        $existing = (clone $table->getQuery())
            ->whereIn($primaryKey, $keys)
            ->pluck($orderColumn, $primaryKey);

        // The dragged keys that survived the scope, still in their new visual order.
        $targets = array_values(array_filter($keys, fn ($key) => $existing->has($key)));

        if ($targets === []) {
            return [];
        }

        $slots = array_map(fn ($key) => $existing->get($key), $targets);

        // Null or duplicated order values give nothing to redistribute — an order
        // column that was never populated, or a table seeded with a constant. Fall
        // back to the client's positions, which is all the ordering there is.
        $usable = ! in_array(null, $slots, true)
            && count(array_unique($slots)) === count($slots);

        if (! $usable) {
            $slots = range(1, count($targets));
        }

        sort($slots);

        return array_combine($targets, $slots);
    }

    /**
     * Authorization hook for a reorder write. The table's base query already scopes
     * WHICH rows can be touched; override this to gate WHETHER the current user may
     * reorder at all (e.g. `return Gate::allows('reorder', $this->getTable()->getModel())`).
     * Defaults to allow.
     *
     * @param  array<int, array{value: string|int, order: int}>  $items
     */
    protected function canReorder(array $items): bool
    {
        return true;
    }

    /**
     * Hook: before row reorder DB update.
     *
     * @param  array<int, array{value: string|int, order: int}>  $items
     */
    protected function beforeReorder(array $items): void {}

    /**
     * Hook: after row reorder DB update.
     *
     * @param  array<int, array{value: string|int, order: int}>  $items
     */
    protected function afterReorder(array $items): void {}

    // ==========================================
    // Column Reorder
    // ==========================================

    /**
     * Handle column drag & drop. Saves to DB per user + model.
     *
     * @param  array<int, string>  $columnOrder  Column names in new order
     */
    public function reorderColumns(array $columnOrder): void
    {
        $table = $this->getTable();

        if (! $table->isColumnReorderable()) {
            return;
        }

        $userId = $this->getReorderableUserId();
        $modelType = $this->getReorderableModelType();
        $tableIdentifier = $this->getReorderableTableIdentifier();

        if ($userId === null || $modelType === null) {
            return;
        }

        $definedNames = array_map(fn ($c) => $c->getName(), $table->getColumns());

        $filtered = array_values(array_filter(
            $columnOrder,
            fn (string $name) => in_array($name, $definedNames, true)
        ));

        if (empty($filtered)) {
            return;
        }

        $this->reorderableColumnOrder = $filtered;

        ReorderableColumnOrder::saveOrder($userId, $modelType, $tableIdentifier, $filtered);
    }

    /**
     * Reset column order to default.
     */
    public function resetColumnOrder(): void
    {
        $this->reorderableColumnOrder = [];

        $userId = $this->getReorderableUserId();
        $modelType = $this->getReorderableModelType();
        $tableIdentifier = $this->getReorderableTableIdentifier();

        if ($userId !== null && $modelType !== null) {
            ReorderableColumnOrder::deleteOrder($userId, $modelType, $tableIdentifier);
        }
    }

    /**
     * Get columns in user-defined order.
     *
     * @return array<int, Column>
     */
    public function getReorderableColumns(): array
    {
        $table = $this->getTable();
        $columns = $table->getColumns();

        if (empty($this->reorderableColumnOrder)) {
            return $columns;
        }

        $indexed = [];
        foreach ($columns as $column) {
            $indexed[$column->getName()] = $column;
        }

        $ordered = [];

        foreach ($this->reorderableColumnOrder as $name) {
            if (isset($indexed[$name])) {
                $ordered[] = $indexed[$name];
                unset($indexed[$name]);
            }
        }

        foreach ($indexed as $column) {
            $ordered[] = $column;
        }

        return $ordered;
    }

    // ==========================================
    // Helpers
    // ==========================================

    /**
     * Check if table is currently in row reorder mode.
     */
    public function isTableReordering(): bool
    {
        return $this->isReordering;
    }

    /**
     * Get the user ID for column order persistence.
     * Override this to customize (e.g. for multi-guard auth).
     */
    protected function getReorderableUserId(): int|string|null
    {
        return auth()->id();
    }

    /**
     * Get the table identifier for column order persistence.
     * Uses the Livewire component class name to distinguish
     * multiple tables over the same model.
     * Override this to customize.
     */
    protected function getReorderableTableIdentifier(): string
    {
        return static::class;
    }

    /**
     * Get the model type key for column order persistence.
     * Uses the table's Eloquent model class.
     */
    protected function getReorderableModelType(): ?string
    {
        $table = $this->getTable();

        try {
            $query = $table->getQuery();

            return get_class($query->getModel());
        } catch (\RuntimeException) {
            return null;
        }
    }

    private function resolveModelClass(Table $table): ?string
    {
        return $this->getReorderableModelType();
    }
}
