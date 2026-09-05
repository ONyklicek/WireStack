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
     * The file the job was queued for is not on the disk it named.
     *
     * Checked before the download rather than left to the reader, so the message
     * can name the disk: by the time {@see CsvImporter::rows()} sees a path it is
     * a local temp file, and "/tmp/wire-import-XYZ is unreadable" tells a worker's
     * log nothing about which upload went missing.
     */
    public static function fileNotFound(string $path, string $disk): self
    {
        return new self("The file [{$path}] does not exist on disk [{$disk}].");
    }

    /**
     * The reader could not open the file it was handed.
     *
     * Loud on purpose, on both the queued and the synchronous path. The
     * alternative — yielding no rows — reports "imported 0 row(s), 0 failed",
     * which is exactly what an empty file produces: the one answer that makes a
     * lost upload look like a successful import. {@see CsvImporter::rows()}
     * still treats a genuinely empty file as zero rows, because that one is
     * true.
     */
    public static function fileNotReadable(string $path): self
    {
        return new self(
            "The file [{$path}] could not be opened for reading, so nothing was imported. ".
            'Check that the path exists and is readable by the process running the import.'
        );
    }

    public static function noModelOrHandler(): self
    {
        return new self('TableImport requires a model() or a createUsing() handler.');
    }
}
