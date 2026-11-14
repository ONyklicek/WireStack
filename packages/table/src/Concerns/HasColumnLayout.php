<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

/**
 * What a user may change about the table's columns beyond showing and hiding
 * them: their width, and which of them stay pinned at the start.
 *
 * Both are kept in the table state (`columns.widths`, `columns.pinned`), so
 * they travel with the remembered layout (rememberColumns()) and with saved
 * views like any other part of the layout.
 */
trait HasColumnLayout
{
    protected bool $resizableColumns = false;

    protected bool $pinnableColumns = false;

    /** Let the user drag a column header's edge to make it wider or narrower. */
    public function resizableColumns(bool $resizable = true): static
    {
        $this->resizableColumns = $resizable;

        return $this;
    }

    public function hasResizableColumns(): bool
    {
        return $this->resizableColumns;
    }

    /** Let the user pin columns to the start of the table from the view menu. */
    public function pinnableColumns(bool $pinnable = true): static
    {
        $this->pinnableColumns = $pinnable;

        return $this;
    }

    public function hasPinnableColumns(): bool
    {
        return $this->pinnableColumns;
    }
}
