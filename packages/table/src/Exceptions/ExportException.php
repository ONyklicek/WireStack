<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;
use Throwable;

/**
 * An export that could not be produced, or could not be written where it was
 * asked to go.
 *
 * The counterpart to {@see ImportException}, and it exists for the same reason:
 * a file operation that fails quietly leaves the caller holding a path to
 * nothing. Every case here is about the state of the filesystem or of the export
 * definition rather than a bad argument at the call site, so `RuntimeException`
 * is the base — which is also what these sites already threw, so an application
 * catching it keeps working (ADR 0022).
 *
 * The writers suppress PHP's own warning before throwing one of these. That is
 * not the warning being ignored: without the suppression Laravel's error handler
 * turns it into an `ErrorException` naming `fopen`, and what reaches the caller
 * talks about a stream rather than about their export. Everything the warning
 * said is in the message, plus which step of the export it was.
 */
final class ExportException extends RuntimeException implements WireException
{
    /**
     * The destination could not be opened for writing.
     *
     * Reached by an unwritable directory, a path that is itself a directory, or
     * a disk with nothing left on it. Loud, because the alternative is an
     * exporter that returns normally and a caller that believes a file is there.
     *
     * `$previous` carries a writer library's own failure whole rather than
     * flattening it to a string: it names the sheet, the row or the encoding
     * step, and whoever handles this needs that, not only the path.
     */
    public static function destinationNotWritable(string $path, ?Throwable $previous = null): self
    {
        return new self("Could not open [{$path}] to write the export to.", 0, $previous);
    }

    /**
     * There is nowhere on this machine to stage the file before storing it.
     *
     * `tempnam()` falls back to the system temp directory whenever the one it is
     * handed is unusable — measured returning a path for `/nonexistent`, `/` and
     * `/dev/null` alike — so `false` means no writable temp directory exists at
     * all.
     */
    public static function noTemporaryFile(): self
    {
        return new self(
            'Could not open a temporary file for the export. The system temp directory '.
            '('.sys_get_temp_dir().') is not writable by the process running the export.'
        );
    }

    /**
     * The exporter returned, but what it wrote cannot be read back.
     *
     * The step between writing and storing. An exporter that wrote nothing is
     * the case this catches, and catching it here is what stops an empty file
     * reaching the disk under a name that promises data.
     */
    public static function unreadableTemporaryFile(string $path): self
    {
        return new self("Could not read back the export that was just written to [{$path}].");
    }

    /**
     * The export was run against no query at all.
     *
     * A definition problem rather than a filesystem one: neither the table nor
     * the call site supplied something to export.
     */
    public static function noQuery(): self
    {
        return new self(
            'No query defined for export. Give the export a table to read from, or pass '.
            'a query to export()/store().'
        );
    }
}
