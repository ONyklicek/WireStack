<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Data;

/**
 * How a dataset is paged.
 *
 * These are the three shapes `WithTable::paginateQuery()` already switches on;
 * modelling all of them from the start is deliberate, because a `DataSource`
 * that only understood length-aware paging would silently be unable to serve
 * the cursor and simple modes the table already offers.
 */
enum PagingMode: string
{
    /** Total count known — the default, and what renders a numbered pager. */
    case LengthAware = 'length_aware';

    /** Next/previous only; no COUNT(*) is issued. */
    case Simple = 'simple';

    /** Keyset paging by cursor; stable under inserts, no offsets. */
    case Cursor = 'cursor';
}
