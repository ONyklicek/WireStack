<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

/**
 * A column that stays at the start of the table while the rest scrolls
 * sideways — the record's name beside forty columns of figures.
 *
 * Declared here by the developer; a user can pin and unpin columns of their
 * own where the table allows it (Table::pinnableColumns()). Either way a pinned
 * column is drawn before the others, in its declared order, and its offset from
 * the start is measured in the browser (wire-table-columns.js), because it is
 * the sum of the widths of the pinned columns before it.
 */
trait CanBePinned
{
    protected bool $pinned = false;

    /** Keep the column at the start of the table while the rest scrolls sideways. */
    public function pinned(bool $pinned = true): static
    {
        $this->pinned = $pinned;

        return $this;
    }

    public function isPinned(): bool
    {
        return $this->pinned;
    }
}
