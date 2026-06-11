<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TableExport
{
    /** @var array<int, Column>|null */
    protected ?array $columns = null;

    /** @var Builder<Model>|null */
    protected ?Builder $query = null;

    protected string $fileName = 'export';

    protected bool $withHeadings = true;

    protected bool $withSummaries = true;

    protected ExportFormat $format = ExportFormat::Csv;

    protected string $csvDelimiter = ',';

    protected string $csvEnclosure = '"';

    protected string $pdfOrientation = 'portrait';

    protected string $pdfPaperSize = 'A4';

    protected ?string $pdfView = null;

    protected ?Closure $modifyQueryCallback = null;

    public static function make(): static
    {
        return new static; // @phpstan-ignore new.static
    }

    /**
     * @param  array<int, Column>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @return array<int, Column>
     */
    public function getColumns(): ?array
    {
        return $this->columns;
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function query(Builder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return Builder<Model>|null
     */
    public function getQuery(): ?Builder
    {
        return $this->query;
    }

    public function fileName(string $fileName): static
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function withHeadings(bool $withHeadings = true): static
    {
        $this->withHeadings = $withHeadings;

        return $this;
    }

    public function hasHeadings(): bool
    {
        return $this->withHeadings;
    }

    /**
     * Include footer summary rows ('query'-scoped column summaries) in the
     * export. Enabled by default; opt out with withSummaries(false).
     */
    public function withSummaries(bool $withSummaries = true): static
    {
        $this->withSummaries = $withSummaries;

        return $this;
    }

    public function hasSummaries(): bool
    {
        return $this->withSummaries;
    }

    public function format(ExportFormat $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function getFormat(): ExportFormat
    {
        return $this->format;
    }

    public function delimiter(string $delimiter): static
    {
        $this->csvDelimiter = $delimiter;

        return $this;
    }

    public function getDelimiter(): string
    {
        return $this->csvDelimiter;
    }

    public function enclosure(string $enclosure): static
    {
        $this->csvEnclosure = $enclosure;

        return $this;
    }

    public function getEnclosure(): string
    {
        return $this->csvEnclosure;
    }

    public function orientation(string $orientation): static
    {
        $this->pdfOrientation = $orientation;

        return $this;
    }

    public function getOrientation(): string
    {
        return $this->pdfOrientation;
    }

    public function paperSize(string $paperSize): static
    {
        $this->pdfPaperSize = $paperSize;

        return $this;
    }

    public function getPaperSize(): string
    {
        return $this->pdfPaperSize;
    }

    public function pdfView(string $view): static
    {
        $this->pdfView = $view;

        return $this;
    }

    public function getPdfView(): ?string
    {
        return $this->pdfView;
    }

    public function modifyQueryUsing(Closure $callback): static
    {
        $this->modifyQueryCallback = $callback;

        return $this;
    }

    public function getModifyQueryCallback(): ?Closure
    {
        return $this->modifyQueryCallback;
    }

    /**
     * Execute the export and return a downloadable response.
     *
     * @param  Builder<Model>|null  $query  Override query (uses internal query if null)
     * @param  array<int, Column>|null  $columns  Override columns (uses internal columns if null)
     */
    public function download(?Builder $query = null, ?array $columns = null): StreamedResponse
    {
        $query = $query ?? $this->query;
        $columns = $columns ?? $this->columns ?? [];

        if ($query === null) {
            throw new \RuntimeException('No query defined for export.');
        }

        if ($this->modifyQueryCallback) {
            $query = ($this->modifyQueryCallback)($query) ?? $query;
        }

        // Filter to only visible columns
        $columns = array_values(array_filter($columns, fn (Column $col) => $col->canView()));

        $exporter = $this->resolveExporter();

        $fullFileName = $this->fileName.'.'.$this->format->extension();

        $summaryRows = $this->withSummaries ? $this->buildSummaryRows($query, $columns) : [];

        return $exporter->export($query, $columns, $fullFileName, $summaryRows);
    }

    /**
     * Build pre-formatted summary rows from the columns' 'query'-scoped
     * summaries — the same totals the footer shows for the full filtered set.
     * Cells render as "Label: value" in the column they belong to; a column
     * with several summaries produces several rows.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, Column>  $columns
     * @return array<int, array<int, string>>
     */
    protected function buildSummaryRows(Builder $query, array $columns): array
    {
        $perColumn = [];
        $maxRows = 0;

        foreach ($columns as $index => $column) {
            $entries = $column->hasSummaryInScope('query')
                ? $column->computeSummaries(collect(), clone $query, ['query'])
                : [];

            $perColumn[$index] = $entries;
            $maxRows = max($maxRows, count($entries));
        }

        $rows = [];

        for ($i = 0; $i < $maxRows; $i++) {
            $row = [];

            foreach (array_keys($columns) as $index) {
                $entry = $perColumn[$index][$i] ?? null;

                if ($entry === null) {
                    $row[] = '';

                    continue;
                }

                $label = (string) ($entry['label'] ?? '');

                $row[] = trim(($label !== '' ? $label.': ' : '').$entry['value']);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    protected function resolveExporter(): Contracts\Exporter
    {
        return match ($this->format) {
            ExportFormat::Csv => new CsvExporter($this->csvDelimiter, $this->csvEnclosure, $this->withHeadings),
            ExportFormat::Excel => new ExcelExporter($this->withHeadings),
            ExportFormat::Pdf => new PdfExporter($this->pdfOrientation, $this->pdfPaperSize, $this->pdfView, $this->withHeadings),
        };
    }
}
