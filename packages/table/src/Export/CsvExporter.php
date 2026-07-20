<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Export\Contracts\Exporter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter implements Exporter
{
    use Concerns\ResolvesExportValue;

    public function __construct(
        protected string $delimiter = ',',
        protected string $enclosure = '"',
        protected bool $withHeadings = true,
    ) {}

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @param  array<int, array<int, string>>  $summaryRows
     */
    public function export(Builder $query, array $columns, string $fileName, array $summaryRows = []): StreamedResponse
    {
        return new StreamedResponse(function () use ($query, $columns, $summaryRows) {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM for UTF-8 Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            if ($this->withHeadings) {
                fputcsv(
                    $handle,
                    array_map(fn (Column $col) => $col->getLabel(), $columns),
                    $this->delimiter,
                    $this->enclosure,
                );
            }

            // Pass the qualified key: the export query carries a relation LEFT JOIN
            // whenever the table is sorted by a relation column, and chunkById's
            // default unqualified `id` cursor is ambiguous against the joined table.
            $model = $query->getModel();

            $query->chunkById(1000, function ($records) use ($handle, $columns) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($columns as $column) {
                        $row[] = $this->resolveColumnValue($column, $record);
                    }
                    fputcsv($handle, $row, $this->delimiter, $this->enclosure);
                }
            }, $model->getQualifiedKeyName(), $model->getKeyName());

            foreach ($summaryRows as $summaryRow) {
                fputcsv($handle, array_map([$this, 'escapeFormula'], $summaryRow), $this->delimiter, $this->enclosure);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    protected function resolveColumnValue(Column $column, Model $record): string
    {
        $value = $this->resolveRawExportValue($column, $record);

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $this->escapeFormula((string) $value);
    }
}
