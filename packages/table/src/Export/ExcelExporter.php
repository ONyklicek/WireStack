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
    use Concerns\ResolvesExportValue;

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
        return static::isAvailable() ? 'xlsx' : 'csv';
    }

    public function writeTo(string $path, Builder $query, array $columns, array $summaryRows = []): void
    {
        if (! static::isAvailable()) {
            (new CsvExporter(withHeadings: $this->withHeadings))
                ->writeTo($path, $query, $columns, $summaryRows);

            return;
        }

        /** @var Writer $writer */
        $writer = new Writer;
        $writer->openToFile($path);

        if ($this->withHeadings) {
            $headerCells = array_map(
                fn (Column $col) => Cell::fromValue(
                    $col->getLabel()
                ),
                $columns
            );
            $writer->addRow(new Row($headerCells));
        }

        // Qualified key cursor: a relation-sorted export query carries a LEFT
        // JOIN where chunkById's default unqualified `id` would be ambiguous.
        $model = $query->getModel();

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
        }, $model->getQualifiedKeyName(), $model->getKeyName());

        foreach ($summaryRows as $summaryRow) {
            $writer->addRow(new Row(array_map(
                fn (string $cell) => Cell::fromValue($cell),
                $summaryRow,
            )));
        }

        $writer->close();
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

        return new StreamedResponse(
            fn () => $this->writeTo('php://output', $query, $columns, $summaryRows),
            200,
            [
                'Content-Type' => ExportFormat::Excel->mimeType(),
                'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
            ],
        );
    }

    protected function resolveColumnValue(Column $column, Model $record): string|int|float|bool
    {
        $value = $this->resolveRawExportValue($column, $record);

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

        // Force literal text for anything OpenSpout would turn into a formula cell
        // (a leading `=`) or a spreadsheet would evaluate on open.
        return $this->escapeFormula((string) $value);
    }
}
