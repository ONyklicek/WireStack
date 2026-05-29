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
     */
    public function export(Builder $query, array $columns, string $fileName): StreamedResponse
    {
        if (! static::isAvailable()) {
            // Fallback to CSV
            $csvFileName = str_replace('.pdf', '.csv', $fileName);

            return (new CsvExporter(withHeadings: $this->withHeadings))
                ->export($query, $columns, $csvFileName);
        }

        // Collect all records (PDF can't stream chunks)
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
        ]);

        $pdf->setPaper($this->paperSize, $this->orientation);

        return new StreamedResponse(function () use ($pdf) {
            echo $pdf->output();
        }, 200, [
            'Content-Type' => ExportFormat::Pdf->mimeType(),
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    protected function resolveColumnValue(Column $column, Model $record): string
    {
        $name = $column->getName();

        if (str_contains($name, '.')) {
            $value = data_get($record, $name);
        } else {
            $value = $record->getAttribute($name);
        }

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
