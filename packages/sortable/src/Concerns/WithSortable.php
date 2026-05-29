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
     * Called by WithTable via method_exists. Returns all records
     * ordered by the sort column when in reorder mode (bypassing
     * search, filters, sorting, and pagination).
     */
    protected function interceptTableRecords(): LengthAwarePaginator|Paginator|CursorPaginator|Collection|null
    {
        $table = $this->getTable();

        if (
            $this->isReordering
            && $table->isReorderable()
            && ! $table->isPaginatedWhileReordering()
        ) {
            $query = $table->getQuery();
            $query->orderBy($table->getOrderColumn(), 'asc');

            return $query->get();
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

        $orderColumn = $table->getOrderColumn();
        $modelClass = $this->resolveModelClass($table);

        if ($modelClass === null) {
            return;
        }

        $this->beforeReorder($items);

        $primaryKey = $table->getPrimaryKey();

        DB::transaction(function () use ($items, $orderColumn, $modelClass, $primaryKey) {
            foreach ($items as $item) {
                $modelClass::where($primaryKey, $item['value'])
                    ->update([$orderColumn => $item['order']]);
            }
        });

        $this->afterReorder($items);

        $this->cachedRecords = null;
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
    protected function getReorderableUserId(): ?int
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
