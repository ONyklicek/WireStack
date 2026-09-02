<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Where a thing sits in a list that something renders.
 *
 * Deliberately **not** query sorting. `Column::sortable()`, `Table::defaultSort()`
 * and `Core\Query\SortClause` decide the order of *records*, which is a database
 * question with a direction and a column name;
 * this decides the order of *declared things* — menu entries, the groups they
 * sit in — which is an integer the author picks and nothing reads back.
 *
 * Lower sorts first, and equal values keep declaration order, which is what
 * makes `sort()` optional rather than mandatory: a menu declared in a sensible
 * order already reads that way.
 */
trait HasSortOrder
{
    protected int $sort = 0;

    /** Position within the list this belongs to. Lower sorts first. */
    public function sort(int $sort): static
    {
        $this->sort = $sort;

        return $this;
    }

    public function getSort(): int
    {
        return $this->sort;
    }
}
