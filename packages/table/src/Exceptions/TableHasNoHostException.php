<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * Thrown when a table is asked to render itself without a Livewire host.
 *
 * A state failure rather than a bad argument — sibling of
 * {@see TableHasNoDataSourceException}, and a RuntimeException for the same
 * reason.
 *
 * A `Table` is a configuration object; the host is what supplies the things a
 * render needs but a definition cannot know — the current state, the page of
 * records, and the component id the client bindings are scoped to. `WithTable`
 * attaches itself on `getTable()`, so a table that reached this exception was
 * built standalone (`Table::make()`) and then cast to a string.
 */
final class TableHasNoHostException extends RuntimeException implements WireException
{
    public static function make(): self
    {
        return new self(
            'A table can only render through its Livewire host. Render the '
            .'component (or its $this->table property) rather than casting a '
            .'standalone Table to a string.'
        );
    }
}
