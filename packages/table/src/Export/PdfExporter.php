<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use Barryvdh\DomPDF\PDF;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
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

    public static function isAvailable(): bool
    {
        return class_exists(\Barryvdh\DomPDF\Facade\Pdf::class);
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

        file_put_contents($path, $this->render($query, $columns, $summaryRows)->output());
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
     * whole set, so the rows are collected before anything is rendered. That is
     * the reason a large PDF export belongs on a queue rather than in a request,
     * and why the memory cost is stated here rather than discovered.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<int, array<int, string>>  $summaryRows
     */
    protected function render(Builder $query, array $columns, array $summaryRows): PDF
    {
        $records = $query->get();

        $headings = $this->withHeadings
            ? array_map(fn (Column $col) => $col->getLabel(), $columns)
            : [];

        $rows = $records->map(function (Model $record) use ($columns) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = $this->resolveColumnValue($column, $record);
            }

            return $row;
        })->all();

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
