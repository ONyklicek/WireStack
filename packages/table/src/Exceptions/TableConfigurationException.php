<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a table is handed an argument its definition cannot accept.
 *
 * Extends InvalidArgumentException because every site it replaces threw one —
 * the SPL base is part of the published behaviour, so it is preserved verbatim.
 * A bad table *state* (rather than a bad argument) is
 * {@see TableHasNoDataSourceException}, which stays a RuntimeException for the
 * same reason.
 */
final class TableConfigurationException extends InvalidArgumentException implements WireException
{
    public static function invalidPollInterval(): self
    {
        return new self('Interval must be like "5s", "500ms", "10m" or "1h".');
    }

    public static function relationPathNotGroupable(string $column): self
    {
        return new self(
            "groupBy() only supports direct columns, got [{$column}]. ".
            'Expose the related value as a column on the query (join/select alias) and group by that.'
        );
    }

    /**
     * @param  array<int, string>  $valid
     */
    public static function unknownSummaryType(string $type, array $valid): self
    {
        return new self(
            "Unknown summary type [{$type}]. Valid types: ".implode(', ', $valid).'.'
        );
    }
}
