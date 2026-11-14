<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use Barryvdh\DomPDF\PDF;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Exceptions\ExportException;
use NyonCode\WireTable\Export\Contracts\Exporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF export using barryvdh/laravel-dompdf (optional dependency).
 *
 * Falls back to CSV if dompdf is not installed.
 */
class PdfExporter implements Exporter
{
    use Concerns\ResolvesExportValue;

    public function __construct(
        protected string $orientation = 'portrait',
        protected string $paperSize = 'A4',
        protected ?string $view = null,
        protected bool $withHeadings = true,
    ) {}

    /**
     * Whether a PDF can actually be produced.
     *
     * The class existing is not the question. This exporter renders through the
     * facade, which resolves `dompdf.wrapper` out of the container, and that
     * binding appears only once the package's service provider has registered.
     * The two normally coincide, because Laravel auto-discovers it — but where
     * they do not (a `dont-discover` entry, an explicit provider list, any
     * context that registers providers by hand) the class alone said "available"
     * and the export died on a BindingResolutionException instead of degrading
     * to CSV the way the docs promise. Ask for what the render actually needs.
     */
    public static function isAvailable(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)
            && app()->bound('dompdf.wrapper');
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<int, array<int, string>>  $summaryRows
     */
    /**
     * Correct a file name to the extension actually being written.
     */
    private function rename(string $fileName): string
    {
        return preg_replace('/\.[^.]+$/', '.'.$this->extension(), $fileName) ?? $fileName;
    }

    public function extension(): string
    {
        return static::isAvailable() ? 'pdf' : 'csv';
    }

    public function writeTo(string $path, Builder $query, array $columns, array $summaryRows = []): void
    {
        if (! static::isAvailable()) {
            (new CsvExporter(withHeadings: $this->withHeadings))
                ->writeTo($path, $query, $columns, $summaryRows);

            return;
        }

        // Same contract as the CSV writer: a path this cannot be written to is an
        // exception naming the export, not a warning naming the filesystem call
        // and not a silent no-op. `file_put_contents` reports failure by return
        // value as well as by warning, so both halves are handled here.
        if (@file_put_contents($path, $this->render($query, $columns, $summaryRows)->output()) === false) {
            throw ExportException::destinationNotWritable($path);
        }
    }

    public function export(Builder $query, array $columns, string $fileName, array $summaryRows = []): StreamedResponse
    {
        if (! static::isAvailable()) {
            // Fallback to CSV, filename included: the reader has to be told what
            // they actually got.
            $csvFileName = $this->rename($fileName);

            return (new CsvExporter(withHeadings: $this->withHeadings))
                ->export($query, $columns, $csvFileName, $summaryRows);
        }

        $pdf = $this->render($query, $columns, $summaryRows);

        return new StreamedResponse(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => ExportFormat::Pdf->mimeType(),
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Build the document.
     *
     * Unlike the other two this one cannot stream: a PDF's layout depends on the
     * whole set, so the rows are collected before anything is rendered. They are
     * read in chunks and held as strings, and past
     * `wire-table.export.pdf_max_rows` the export refuses
     * ({@see ExportException::tooManyRowsForPdf()}) rather than running out of
     * memory halfway.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<int, array<int, string>>  $summaryRows
     */
    protected function render(Builder $query, array $columns, array $summaryRows): PDF
    {
        $headings = $this->withHeadings
            ? array_map(fn (Column $col) => $col->getLabel(), $columns)
            : [];

        // Read in chunks and kept as strings: the document needs every row, but
        // not every model — a few hundred hydrated at a time, then let go. The
        // cap is checked while reading, so a table that grew past it fails on
        // its first row too many rather than after rendering what it could.
        $limit = max(1, (int) config('wire-table.export.pdf_max_rows', 5000));
        $rows = [];

        foreach ($query->lazy(500) as $record) {
            if (count($rows) >= $limit) {
                throw ExportException::tooManyRowsForPdf($limit);
            }

            $row = [];

            foreach ($columns as $column) {
                $row[] = $this->resolveColumnValue($column, $record);
            }

            $rows[] = $row;
        }

        $viewName = $this->view ?? 'wire-table::export.pdf';

        /** @var PDF $pdf */
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView($viewName, [
            'headings' => $headings,
            'rows' => $rows,
            'columns' => $columns,
            'summaryRows' => $summaryRows,
        ]);

        $pdf->setPaper($this->paperSize, $this->orientation);

        return $pdf;
    }

    protected function resolveColumnValue(Column $column, Model $record): string
    {
        $value = $this->resolveRawExportValue($column, $record);

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
