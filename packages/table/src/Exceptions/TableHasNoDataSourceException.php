<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a table is queried before it has been given anything to query.
 *
 * A state failure rather than a bad argument — the table is asked for rows at a
 * point where neither `->model()` nor `->query()` has been set — so it stays a
 * RuntimeException, as it has always been.
 */
final class TableHasNoDataSourceException extends RuntimeException implements WireException
{
    public static function make(): self
    {
        return new self('No model or query defined for table.');
    }
}
