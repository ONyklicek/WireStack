<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

/**
 * Repeatable entry — renders a nested entry schema once per item of an
 * iterable state (e.g. a `hasMany` relation or an array of rows).
 */
class RepeatableEntry extends Entry
{
    /** @var array<int, Entry> */
    protected array $schema = [];

    protected int $columns = 1;

    protected bool $contained = true;

    /**
     * @param  array<int, Entry>  $components
     */
    public function schema(array $components): static
    {
        $this->schema = $components;

        return $this;
    }

    /**
     * @return array<int, Entry>
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    public function columns(int $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function contained(bool $condition = true): static
    {
        $this->contained = $condition;

        return $this;
    }

    public function isContained(): bool
    {
        return $this->contained;
    }

    /**
     * One row per state item, each a fresh clone of the schema bound to the
     * item record.
     *
     * @return array<int, array<int, Entry>>
     */
    public function getRows(): array
    {
        $items = $this->getState();

        if (is_iterable($items) && ! is_array($items)) {
            $items = iterator_to_array($items);
        }

        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $item) {
            $entries = [];

            foreach ($this->schema as $entry) {
                $clone = clone $entry;
                $clone->record($item);
                $entries[] = $clone;
            }

            $rows[] = $entries;
        }

        return $rows;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.repeatable';
    }
}
