<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a fill request cannot be read as one.
 *
 * An InvalidArgumentException because the payload is an argument the endpoint
 * cannot accept — it arrives from the browser, so a malformed one is a bad
 * request rather than a bad server state. `fillTableCells()` catches it at the
 * Livewire boundary and answers the client with the wire failure shape; nothing
 * below that layer returns an error array.
 */
final class FillRequestException extends InvalidArgumentException implements WireException
{
    public static function malformed(): self
    {
        return new self('A fill request must be a list of {column, value, records} entries.');
    }

    public static function emptyColumn(): self
    {
        return new self('A fill entry must name the column it writes.');
    }

    public static function noRecords(string $column): self
    {
        return new self("The fill entry for [{$column}] names no records to write.");
    }

    public static function tooManyRecords(int $requested, int $max): self
    {
        return new self("A fill may write at most {$max} rows at once, {$requested} requested.");
    }
}
