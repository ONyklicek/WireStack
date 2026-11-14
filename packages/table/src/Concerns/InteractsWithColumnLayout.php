<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Support\ColumnLayout;
use NyonCode\WireTable\Table;

/**
 * The component's half of column widths and pinning: the two endpoints the
 * browser calls, and the readings the render plan asks for.
 *
 * Every value that arrives is read against the table as it is declared now,
 * through {@see ColumnLayout}.
 */
trait InteractsWithColumnLayout
{
    /**
     * Set a column's width (px), or put it back to its own with null.
     */
    public function resizeTableColumn(string $column, ?int $width): void
    {
        $table = $this->getTable();

        if (! $table->hasResizableColumns() || ! $this->layoutColumn($table, $column) instanceof Column) {
            return;
        }

        $widths = $this->tableColumnWidths();

        if ($width === null) {
            unset($widths[$column]);
        } else {
            $widths[$column] = ColumnLayout::clamp($width);
        }

        $this->tableState->set('columns.widths', $widths);
        $this->markTableViewChanged();
        $this->persistViewPreferences();
    }

    /**
     * Pin a column to the start of the table, or unpin it. A column the
     * developer pinned (Column::pinned()) stays pinned.
     */
    public function toggleColumnPin(string $column): void
    {
        $table = $this->getTable();
        $target = $this->layoutColumn($table, $column);

        if (! $table->hasPinnableColumns() || ! $target instanceof Column || $target->isPinned()) {
            return;
        }

        $pinned = $this->userPinnedColumns();
        $pinned = in_array($column, $pinned, true)
            ? array_values(array_diff($pinned, [$column]))
            : [...$pinned, $column];

        $this->tableState->set('columns.pinned', $pinned);
        $this->markTableViewChanged();
        $this->persistViewPreferences();
    }

    /**
     * The widths the user set, by column name, for columns that may still have one.
     *
     * @return array<string, int>
     */
    public function tableColumnWidths(): array
    {
        return ColumnLayout::widths($this->tableState->get('columns.widths', []), $this->getTable());
    }

    /**
     * The columns the user pinned, that still exist and may still be pinned.
     *
     * @return list<string>
     */
    public function userPinnedColumns(): array
    {
        return ColumnLayout::pins($this->tableState->get('columns.pinned', []), $this->getTable());
    }

    /** Whether a column is pinned — by the developer, or by this user. */
    public function isColumnPinned(Column $column): bool
    {
        return $column->isPinned() || in_array($column->getName(), $this->userPinnedColumns(), true);
    }

    private function layoutColumn(Table $table, string $name): ?Column
    {
        foreach ($table->getColumns() as $column) {
            if ($column->getName() === $name && $column->canView()) {
                return $column;
            }
        }

        return null;
    }
}
