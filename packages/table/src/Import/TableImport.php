<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use NyonCode\WireTable\Export\TableExport;
use NyonCode\WireTable\Import\Contracts\Importer;
use RuntimeException;

/**
 * Configures and runs a CSV import into an Eloquent model.
 *
 * The mirror of {@see TableExport}: declare the target
 * model and a set of {@see ImportColumn}s, then {@see import()} a file. Each row
 * is mapped by header, cast, validated per column, and persisted (create, or
 * update-or-create when {@see updateExisting()} is set, or a custom
 * {@see createUsing()} handler). Per-row validation failures are collected in the
 * returned {@see ImportResult} instead of aborting the run.
 */
class TableImport
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var array<int, ImportColumn> */
    protected array $columns = [];

    protected string $delimiter = ',';

    protected string $enclosure = '"';

    /** @var array<int, string> Attributes used to match existing records (updateOrCreate). */
    protected array $updateExisting = [];

    protected ?Closure $createUsing = null;

    public static function make(): static
    {
        return new static; // @phpstan-ignore new.static
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param  array<int, ImportColumn>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * @return array<int, ImportColumn>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function delimiter(string $delimiter): static
    {
        $this->delimiter = $delimiter;

        return $this;
    }

    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    public function enclosure(string $enclosure): static
    {
        $this->enclosure = $enclosure;

        return $this;
    }

    /**
     * Update rows matched by the given attributes instead of always creating.
     *
     * @param  array<int, string>  $attributes
     */
    public function updateExisting(array $attributes): static
    {
        $this->updateExisting = $attributes;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getUpdateExisting(): array
    {
        return $this->updateExisting;
    }

    /**
     * Persist each valid row with a custom handler (receives the mapped `$data`).
     */
    public function createUsing(Closure $callback): static
    {
        $this->createUsing = $callback;

        return $this;
    }

    /**
     * Run the import and return a per-row summary.
     */
    public function import(string $filePath): ImportResult
    {
        $result = new ImportResult;
        $mapping = null;
        $rowNumber = 1;

        foreach ($this->resolveImporter()->rows($filePath) as $row) {
            if ($mapping === null) {
                $mapping = $this->resolveMapping(array_keys($row));
            }

            $data = $this->mapRow($row, $mapping);

            $validator = Validator::make($data, $this->validationRules(), [], $this->validationAttributes());

            if ($validator->fails()) {
                $result->addFailure($rowNumber, $validator->errors()->all());
                $rowNumber++;

                continue;
            }

            $this->persist($data);
            $result->addImported();
            $rowNumber++;
        }

        return $result;
    }

    protected function resolveImporter(): Importer
    {
        return new CsvImporter($this->delimiter, $this->enclosure);
    }

    /**
     * Resolve which file header feeds each column, once, from the header row.
     *
     * @param  array<int, string>  $headers
     * @return array<string, string|null> Column name => source header (or null when unmapped).
     */
    protected function resolveMapping(array $headers): array
    {
        $mapping = [];
        $missing = [];

        foreach ($this->columns as $column) {
            $header = $column->resolveHeader($headers);

            if ($header === null && $column->isRequiredMapping()) {
                $missing[] = $column->getLabel();
            }

            $mapping[$column->getName()] = $header;
        }

        if ($missing !== []) {
            throw new RuntimeException('Missing required column(s) in the imported file: '.implode(', ', $missing).'.');
        }

        return $mapping;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string|null>  $mapping
     * @return array<string, mixed>
     */
    protected function mapRow(array $row, array $mapping): array
    {
        $data = [];

        foreach ($this->columns as $column) {
            $header = $mapping[$column->getName()];

            if ($header === null) {
                continue;
            }

            $data[$column->getName()] = $column->castState($row[$header] ?? null);
        }

        return $data;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function validationRules(): array
    {
        $rules = [];

        foreach ($this->columns as $column) {
            if ($column->getRules() !== []) {
                $rules[$column->getName()] = $column->getRules();
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        $attributes = [];

        foreach ($this->columns as $column) {
            $attributes[$column->getName()] = $column->getLabel();
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persist(array $data): void
    {
        if ($this->createUsing !== null) {
            ($this->createUsing)($data);

            return;
        }

        if ($this->model === null) {
            throw new RuntimeException('TableImport requires a model() or a createUsing() handler.');
        }

        $query = $this->model::query();

        if ($this->updateExisting !== []) {
            $keyBy = array_intersect_key($data, array_flip($this->updateExisting));

            $query->updateOrCreate($keyBy, $data);

            return;
        }

        $query->create($data);
    }
}
