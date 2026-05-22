<?php

declare(strict_types=1);

namespace NyonCode\WireSortable;

use NyonCode\WireTable\Table;

/**
 * Extended Table with reorderable rows and columns.
 *
 * Row reorder: toggle mode with drag & drop, saves to DB order column.
 * Column reorder: per-user column ordering, persisted to DB.
 *
 * Usage:
 *   SortableTable::make()
 *       ->model(Task::class)
 *       ->reorderable('sort_order')
 *       ->columnReorderable()
 *       ->columns([...])
 */
class SortableTable extends Table
{
    protected bool $reorderable = false;

    protected string $orderColumn = 'sort_order';

    protected bool $paginatedWhileReordering = false;

    protected bool $columnReorderable = false;

    /**
     * Enable drag & drop row reordering.
     *
     * @param  string|null  $orderColumn  DB column storing the sort position
     * @param  bool  $condition  Conditionally enable
     */
    public function reorderable(?string $orderColumn = null, bool $condition = true): static
    {
        $this->reorderable = $condition;

        if ($orderColumn !== null) {
            $this->orderColumn = $orderColumn;
        }

        return $this;
    }

    public function isReorderable(): bool
    {
        return $this->reorderable;
    }

    public function getOrderColumn(): string
    {
        return $this->orderColumn;
    }

    /**
     * Keep pagination enabled while in row reorder mode.
     */
    public function paginatedWhileReordering(bool $enabled = true): static
    {
        $this->paginatedWhileReordering = $enabled;

        return $this;
    }

    public function isPaginatedWhileReordering(): bool
    {
        return $this->paginatedWhileReordering;
    }

    /**
     * Enable user-specific column reordering.
     *
     * Column order is persisted per user + model in the DB.
     */
    public function columnReorderable(bool $enabled = true): static
    {
        $this->columnReorderable = $enabled;

        return $this;
    }

    public function isColumnReorderable(): bool
    {
        return $this->columnReorderable;
    }

    /**
     * Get the Blade view name for this table.
     *
     * Called by WithTable via method_exists on the table instance.
     */
    public function getViewName(): string
    {
        if ($this->isReorderable() || $this->isColumnReorderable()) {
            return 'wire-sortable::tables.index';
        }

        return 'wire-table::tables.index';
    }
}
