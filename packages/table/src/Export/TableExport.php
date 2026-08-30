<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Export;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
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
        [$exporter, $query, $columns, $summaryRows] = $this->prepare($query, $columns);

        return $exporter->export($query, $columns, $this->fullFileName($exporter), $summaryRows);
    }

    /**
     * Write the export to a disk instead of returning a download.
     *
     * The delivery a queued export needs: a job has no response to return, so it
     * writes the file and hands back a path for the completion notification to
     * link to. Everything before the last line is the same preparation
     * {@see download()} does, which is why it is one method — an export that
     * chose different columns depending on how it was delivered would be a bug
     * nobody could see until they compared two files.
     *
     * @param  Builder<Model>|null  $query
     * @param  array<int, Column>|null  $columns
     * @param  string|null  $disk  Defaults to the filesystem's own default.
     * @return string The path within the disk.
     */
    public function store(?Builder $query = null, ?array $columns = null, ?string $disk = null, string $directory = 'exports'): string
    {
        [$exporter, $query, $columns, $summaryRows] = $this->prepare($query, $columns);

        $storage = Storage::disk($disk);
        $path = trim($directory, '/').'/'.$this->fullFileName($exporter);

        // Written through a temp file rather than straight to the disk: a disk
        // may be S3, and the exporters write with fopen()/openToFile(), which
        // need a real stream. The upload is one put() afterwards.
        $temp = tempnam(sys_get_temp_dir(), 'wire-export');

        if ($temp === false) {
            throw new \RuntimeException('Could not open a temporary file for the export.');
        }

        try {
            $exporter->writeTo($temp, $query, $columns, $summaryRows);

            $handle = fopen($temp, 'r');

            if ($handle === false) {
                throw new \RuntimeException('Could not read back the export that was just written.');
            }

            $storage->put($path, $handle);

            if (is_resource($handle)) {
                fclose($handle);
            }
        } finally {
            @unlink($temp);
        }

        return $path;
    }

    /**
     * The name the file gets.
     *
     * Ask the exporter when there is one: it, not the format, knows whether it
     * could honour the format at all. See {@see Contracts\Exporter::extension()}.
     */
    public function fullFileName(?Contracts\Exporter $exporter = null): string
    {
        return $this->fileName.'.'.($exporter?->extension() ?? $this->format->extension());
    }

    /**
     * Everything both deliveries need, resolved once.
     *
     * @param  Builder<Model>|null  $query
     * @param  array<int, Column>|null  $columns
     * @return array{0: Contracts\Exporter, 1: Builder<Model>, 2: array<int, Column>, 3: array<int, array<int, string>>}
     */
    protected function prepare(?Builder $query, ?array $columns): array
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

        return [
            $this->resolveExporter(),
            $query,
            $columns,
            $this->withSummaries ? $this->buildSummaryRows($query, $columns) : [],
        ];
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
