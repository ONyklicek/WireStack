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

    protected bool $alwaysReorderable = false;

    protected ?string $orderColumn = null;

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

    /**
     * Always keep row reordering active (no toggle button); implies reorderable.
     *
     * @param  string|null  $orderColumn  DB column storing the sort position
     */
    public function alwaysReorderable(?string $orderColumn = null): static
    {
        $this->reorderable = true;
        $this->alwaysReorderable = true;

        if ($orderColumn !== null) {
            $this->orderColumn = $orderColumn;
        }

        return $this;
    }

    public function isAlwaysReorderable(): bool
    {
        return $this->alwaysReorderable;
    }

    public function getOrderColumn(): string
    {
        if ($this->orderColumn !== null) {
            return $this->orderColumn;
        }

        // Read the package config when a container is available; fall back to the
        // documented default so the table works without a booted app (unit tests).
        if (app()->bound('config')) {
            return config('wire-sortable.order_column', 'sort_order');
        }

        return 'sort_order';
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
