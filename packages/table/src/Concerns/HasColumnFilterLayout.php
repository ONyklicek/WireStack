<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Enums\ColumnFilterLayout;

/**
 * How the column header filters are reached: the table's
 * {@see ColumnFilterLayout} (per table, else
 * `wire-table.defaults.column_filter_layout`), and the cell menu that sets a
 * filter from a value in the rows.
 */
trait HasColumnFilterLayout
{
    protected ?ColumnFilterLayout $columnFilterLayout = null;

    protected bool $cellFilterMenu = false;

    /** Where the column header filters live: a row under the header, or a popover per column. */
    public function columnFilterLayout(ColumnFilterLayout|string $layout): static
    {
        $this->columnFilterLayout = $layout instanceof ColumnFilterLayout
            ? $layout
            : ColumnFilterLayout::from($layout);

        return $this;
    }

    public function getColumnFilterLayout(): ColumnFilterLayout
    {
        return $this->columnFilterLayout
            ?? ColumnFilterLayout::tryFrom((string) config('wire-table.defaults.column_filter_layout', 'row'))
            ?? ColumnFilterLayout::Row;
    }

    /**
     * Right-clicking a cell of a column with a header filter offers "Only this
     * value" and, where the filter has a negative operator, "Exclude this value".
     * On a row that has its own context menu that menu wins.
     */
    public function cellFilterMenu(bool $enabled = true): static
    {
        $this->cellFilterMenu = $enabled;

        return $this;
    }

    public function hasCellFilterMenu(): bool
    {
        return $this->cellFilterMenu;
    }
}
