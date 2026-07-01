<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Import;

/**
 * Outcome of a {@see TableImport} run: how many rows were imported and the
 * per-row validation errors for the rows that were skipped.
 */
class ImportResult
{
    protected int $imported = 0;

    /** @var array<int, array{row: int, errors: array<int, string>}> */
    protected array $failures = [];

    public function addImported(): void
    {
        $this->imported++;
    }

    /**
     * @param  array<int, string>  $errors
     */
    public function addFailure(int $row, array $errors): void
    {
        $this->failures[] = ['row' => $row, 'errors' => $errors];
    }

    public function getImported(): int
    {
        return $this->imported;
    }

    public function getFailedCount(): int
    {
        return count($this->failures);
    }

    /**
     * @return array<int, array{row: int, errors: array<int, string>}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    /**
     * Total rows processed (imported + failed).
     */
    public function getTotal(): int
    {
        return $this->imported + $this->getFailedCount();
    }
}
