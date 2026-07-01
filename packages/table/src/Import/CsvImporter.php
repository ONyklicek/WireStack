<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import;

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
     */
    public function rows(string $filePath): iterable
    {
        // Suppress the open warning; an unreadable path simply yields no rows.
        $handle = @fopen($filePath, 'r');

        if ($handle === false) {
            return;
        }

        try {
            $headerRow = fgetcsv($handle, 0, $this->delimiter, $this->enclosure);

            if (! is_array($headerRow)) {
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
