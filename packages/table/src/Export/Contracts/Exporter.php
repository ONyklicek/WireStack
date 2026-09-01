<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface Exporter
{
    /**
     * Write the export to a path.
     *
     * The one place rows are produced. `php://output` is a path like any other,
     * so {@see export()} is this method inside a response wrapper rather than a
     * second implementation — which matters because a queued export cannot
     * return a download at all, and two copies of "turn records into a file"
     * drift the moment one of them learns about a column type.
     *
     * A path that cannot be written to is a `RuntimeException` naming that path.
     * It is not a silent return: the caller of a queued or stored export has no
     * response to read, so "wrote nothing" and "wrote the file" have to be
     * distinguishable, and they are only distinguishable if failure is thrown.
     *
     * @param  string  $path  Any stream PHP can open: a file, `php://output`, a temp handle.
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<int, array<int, string>>  $summaryRows
     *
     * @throws \RuntimeException When the path cannot be opened for writing.
     */
    public function writeTo(string $path, Builder $query, array $columns, array $summaryRows = []): void;

    /**
     * The extension this exporter will actually produce.
     *
     * Not always the format's. An optional library that is not installed makes
     * the exporter degrade to CSV, and the reader has to be told what they
     * actually got — a file named `.xlsx` holding CSV is a lie that surfaces
     * much later, when someone finally opens it.
     */
    public function extension(): string;

    /**
     * Export the query results to a downloadable response.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<int, array<int, string>>  $summaryRows  Pre-formatted summary
     *                                                       rows (one cell per column) appended after the data rows.
     */
    public function export(Builder $query, array $columns, string $fileName, array $summaryRows = []): StreamedResponse;
}
