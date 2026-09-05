<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import;

use NyonCode\WireTable\Exceptions\ImportException;
use NyonCode\WireTable\Import\Contracts\Importer;

/**
 * Reads a CSV file into header-keyed rows.
 *
 * The first line is treated as the header. Each data row is aligned to the
 * header (missing trailing cells become empty strings, extras are dropped), so
 * every yielded row has exactly the header's keys.
 */
class CsvImporter implements Importer
{
    public function __construct(
        protected string $delimiter = ',',
        protected string $enclosure = '"',
    ) {}

    /**
     * @return iterable<int, array<string, string>>
     *
     * @throws ImportException When the path cannot be opened for reading.
     */
    public function rows(string $filePath): iterable
    {
        // Suppressed on purpose, the same trade the exporters make: an
        // unopenable path raises a warning naming `fopen`, and what should reach
        // the caller names the import. Nothing is lost — the path is in the
        // exception.
        $handle = @fopen($filePath, 'r');

        if ($handle === false) {
            // Not "no rows". An ImportResult of 0 imported and 0 failed is what
            // an empty file legitimately produces, so answering it here makes a
            // missing upload indistinguishable from one that held nothing —
            // reported to the user as a clean, successful import of nothing.
            throw ImportException::fileNotReadable($filePath);
        }

        try {
            $headerRow = fgetcsv($handle, 0, $this->delimiter, $this->enclosure);

            if (! is_array($headerRow)) {
                // A genuinely empty file, which is a real (if useless) import of
                // zero rows rather than a failure — unlike the case above.
                return;
            }

            $headers = $this->normalizeHeaders($headerRow);
            $count = count($headers);

            while (($row = fgetcsv($handle, 0, $this->delimiter, $this->enclosure)) !== false) {
                if ($row === [null]) {
                    // Blank line — fgetcsv yields a single null cell; skip it.
                    continue;
                }

                $values = array_pad(array_slice($row, 0, $count), $count, '');

                yield array_combine($headers, array_map(
                    static fn ($value): string => (string) ($value ?? ''),
                    $values,
                ));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $headerRow
     * @return array<int, string>
     */
    protected function normalizeHeaders(array $headerRow): array
    {
        $headers = [];

        foreach ($headerRow as $index => $header) {
            $header = (string) ($header ?? '');

            if ($index === 0) {
                // Strip a UTF-8 BOM the file may start with.
                $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
            }

            $headers[] = trim($header);
        }

        return $headers;
    }
}
