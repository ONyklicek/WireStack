<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Export\Contracts\Exporter;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel export using OpenSpout (optional dependency).
 *
 * Falls back to CSV if OpenSpout is not installed.
 */
class ExcelExporter implements Exporter
{
    public function __construct(
        protected bool $withHeadings = true,
    ) {}

    public static function isAvailable(): bool
    {
        return class_exists(Writer::class);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     */
    public function export(Builder $query, array $columns, string $fileName): StreamedResponse
    {
        if (! static::isAvailable()) {
            // Fallback to CSV
            $csvFileName = str_replace('.xlsx', '.csv', $fileName);

            return (new CsvExporter(withHeadings: $this->withHeadings))
                ->export($query, $columns, $csvFileName);
        }

        return new StreamedResponse(function () use ($query, $columns) {
            /** @var Writer $writer */
            $writer = new Writer;
            $writer->openToFile('php://output');

            if ($this->withHeadings) {
                $headerCells = array_map(
                    fn (Column $col) => Cell::fromValue(
                        $col->getLabel()
                    ),
                    $columns
                );
                $writer->addRow(new Row($headerCells));
            }

            $query->chunkById(1000, function ($records) use ($writer, $columns) {
                foreach ($records as $record) {
                    $cells = [];
                    foreach ($columns as $column) {
                        $cells[] = Cell::fromValue(
                            $this->resolveColumnValue($column, $record)
                        );
                    }
                    $writer->addRow(new Row($cells));
                }
            });

            $writer->close();
        }, 200, [
            'Content-Type' => ExportFormat::Excel->mimeType(),
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    protected function resolveColumnValue(Column $column, Model $record): string|int|float|bool
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

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        return (string) $value;
    }
}
