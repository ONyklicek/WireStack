<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A table over a custom data source was asked for something only a query can do.
 *
 * Search, filters, sorting, pagination, footer summaries and selection go
 * through the source. What does not — grouping, sub-rows, exports, the fill
 * handle, a filter defined as an Eloquent callback — used to die on "No model
 * or query defined for table.", which names neither the feature nor the fix.
 */
final class CustomDataSourceException extends RuntimeException implements WireException
{
    public static function needsQuery(string $source): self
    {
        return new self(
            "This table reads its rows from [{$source}], and something asked it for an Eloquent query. "
            .'A custom data source answers search, filters, sorting, pagination, summaries and selection; '
            .'grouping, sub-rows, exports, the fill handle and anything else built on a query need '
            .'->model() or ->query() instead.'
        );
    }

    public static function filterNeedsQuery(string $filter, string $source): self
    {
        return new self(
            "The filter [{$filter}] applies itself as an Eloquent query, and this table reads its rows from [{$source}]. "
            .'A custom data source can only be filtered by clauses it evaluates itself: use a filter that maps to '
            .'one (SelectFilter, TextFilter, NumberRangeFilter), or filter the rows before handing them to the source.'
        );
    }

    public static function readOnlyRow(): self
    {
        return new self(
            'A row of a custom data source has no table behind it and cannot be saved or deleted. '
            .'Write the change through whatever owns the data, then refresh the table.'
        );
    }
}
