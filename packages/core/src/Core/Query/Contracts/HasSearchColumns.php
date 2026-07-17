<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Query\Contracts;

/**
 * A component that is searched across several columns rather than its own one.
 *
 * The planner searches `getColumnName()` by default, which is right for an
 * ordinary column. A composite one — a stacked cell showing a name over an
 * email, a split cell — displays several attributes, and a user searching it
 * means any of them. Without this the extra columns are simply never searched.
 */
interface HasSearchColumns
{
    /**
     * Columns to search instead of the component's own.
     *
     * An empty list means "no opinion": the planner falls back to
     * `getColumnName()`, so implementing this never silently disables search.
     *
     * @return array<int, string>
     */
    public function getSearchColumns(): array;
}
