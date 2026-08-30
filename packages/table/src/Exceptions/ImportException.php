<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireTable\Import\CsvImporter;
use RuntimeException;

/**
 * Thrown when an import cannot proceed against the file it was given, or
 * against its own definition.
 *
 * An import writes to the database, so every one of these aborts before the
 * first row: a half-applied import is far worse than a rejected one.
 */
final class ImportException extends RuntimeException implements WireException
{
    /**
     * @param  array<int, string>  $missing
     */
    public static function missingColumns(array $missing): self
    {
        return new self('Missing required column(s) in the imported file: '.implode(', ', $missing).'.');
    }

    /**
     * @param  array<int, string>  $unmapped
     */
    public static function unmappedUpdateAttributes(array $unmapped): self
    {
        return new self(
            'The updateExisting() attribute(s) ['.implode(', ', $unmapped).'] are not mapped to any column in the imported file.'
        );
    }

    /**
     * The file the job was queued for is gone.
     *
     * Loud on purpose. {@see CsvImporter::rows()}
     * treats an unreadable path as "no rows", which is right for a synchronous
     * run the user is watching — and a lie for a queued one, where "imported 0
     * row(s), 0 failed" is indistinguishable from an empty file. A worker that
     * cannot find the upload must fail and retry, not report a clean no-op.
     */
    public static function fileNotFound(string $path, string $disk): self
    {
        return new self("The file [{$path}] does not exist on disk [{$disk}].");
    }

    public static function noModelOrHandler(): self
    {
        return new self('TableImport requires a model() or a createUsing() handler.');
    }
}
