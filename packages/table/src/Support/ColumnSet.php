<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use NyonCode\WireTable\Columns\Column;
use Traversable;

/**
 * A table's columns as one thing, rather than an array everybody re-walks.
 *
 * The set answers the questions that were previously spelled out wherever they
 * were needed — the column with this name, the searchable ones, the sortable
 * ones, the names — and it answers the lookup from a map it builds once instead
 * of scanning.
 *
 * **First declaration wins on a duplicate name**, which is what the scan it
 * replaces did. Two columns sharing a name is a configuration mistake rather
 * than a feature, but it must not start resolving to the other one.
 *
 * The incoming array's keys are kept as given: `all()` hands back exactly what
 * was passed to `Table::columns()`, so nothing downstream has to care that a
 * set is in the middle now.
 *
 * @implements IteratorAggregate<array-key, Column>
 */
final class ColumnSet implements Countable, IteratorAggregate
{
    /** @var array<array-key, Column> */
    private array $columns;

    /**
     * Built on first lookup.
     *
     * @var array<string, Column>|null
     */
    private ?array $byName = null;

    /**
     * @param  array<array-key, Column>  $columns
     */
    public function __construct(array $columns = [])
    {
        $this->columns = $columns;
    }

    /**
     * @param  array<array-key, Column>  $columns
     */
    public static function make(array $columns = []): self
    {
        return new self($columns);
    }

    /**
     * @return array<array-key, Column>
     */
    public function all(): array
    {
        return $this->columns;
    }

    /**
     * The column with this name, or null.
     *
     * The canonical lookup — the Livewire host and the fill writer both resolve
     * a client-supplied column name here rather than scanning the set
     * themselves.
     */
    public function find(string $name): ?Column
    {
        if ($this->byName === null) {
            $this->byName = [];

            foreach ($this->columns as $column) {
                $this->byName[$column->getName()] ??= $column;
            }
        }

        return $this->byName[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return $this->find($name) !== null;
    }

    /**
     * @return array<array-key, string>
     */
    public function names(): array
    {
        return array_map(fn (Column $column) => $column->getName(), $this->columns);
    }

    /**
     * @return array<int, Column>
     */
    public function searchable(): array
    {
        return array_values(array_filter($this->columns, fn (Column $column) => $column->isSearchable()));
    }

    /**
     * @return array<int, Column>
     */
    public function sortable(): array
    {
        return array_values(array_filter($this->columns, fn (Column $column) => $column->isSortable()));
    }

    public function isEmpty(): bool
    {
        return $this->columns === [];
    }

    public function count(): int
    {
        return count($this->columns);
    }

    /**
     * @return Traversable<array-key, Column>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->columns);
    }
}
