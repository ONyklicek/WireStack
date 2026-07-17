<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Support\Collection;
use NyonCode\WireTable\Services\AggregateSubqueries;

/**
 * Selecting records for bulk actions.
 *
 * A host concern: these are the endpoints the table's checkboxes call, so they
 * stay on the Livewire component — this splits the section out of the 3,500-line
 * WithTable rather than moving it away from its host.
 *
 * The selection is an explicit set of keys, deliberately unaffected by the
 * table's filters and sort. It is memoised per request because a selection-scope
 * summary, a grand total and a bulk modal can each ask for the set within one
 * render; the memo is dropped whenever the selection mutates or the table cache
 * is invalidated.
 */
trait CanSelectRecords
{
    /** @var Collection|null Memoized selected records — cleared when the selection mutates */
    protected ?Collection $cachedSelectedRecords = null;

    /**
     * Toggle record selection
     */
    public function toggleRecordSelection(string $key): void
    {
        $selected = $this->tableState->get('selection.records', []);
        $index = array_search($key, $selected, true);

        if ($index !== false) {
            unset($selected[$index]);
            $selected = array_values($selected);
        } else {
            $selected[] = $key;
        }

        $this->tableState->set('selection.records', $selected);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Select all visible records
     */
    public function selectAllRecords(): void
    {
        $records = $this->getTableRecords();
        $primaryKey = $this->getTable()->getPrimaryKey();

        $selected = [];
        foreach ($records as $record) {
            $selected[] = (string) $record->{$primaryKey};
        }

        $this->tableState->set('selection.records', $selected);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Check if record is selected
     */
    public function isRecordSelected(string $key): bool
    {
        $selected = $this->tableState->get('selection.records', []);

        return in_array($key, $selected, true);
    }

    /**
     * Get selected records count
     */
    public function getSelectedRecordsCount(): int
    {
        return count($this->tableState->get('selection.records', []));
    }

    /**
     * Check if some (but not all) visible records are selected
     */
    public function areSomeVisibleSelected(): bool
    {
        if (empty($this->tableState->get('selection.records', []))) {
            return false;
        }

        return ! $this->areAllVisibleSelected();
    }

    /**
     * Check if all visible records are selected
     */
    public function areAllVisibleSelected(): bool
    {
        $records = $this->getTableRecords();

        if ($records->isEmpty()) {
            return false;
        }

        $selected = $this->tableState->get('selection.records', []);
        $primaryKey = $this->getTable()->getPrimaryKey();

        foreach ($records as $record) {
            $key = (string) $record->{$primaryKey};
            if (! in_array($key, $selected, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get array of selected record keys
     */
    public function getSelectedRecordKeys(): array
    {
        return $this->tableState->get('selection.records', []);
    }

    /**
     * Deselect all records
     */
    public function deselectAllRecords(): void
    {
        $this->tableState->set('selection.records', []);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Get Collection of selected records (fetched from database).
     *
     * Memoized per request — selection-scope summaries, grand totals, and bulk
     * modals may all ask for the set within one render. The memo is cleared
     * whenever the selection mutates or the table cache is invalidated.
     */
    public function getSelectedRecords(): Collection
    {
        if ($this->cachedSelectedRecords !== null) {
            return $this->cachedSelectedRecords;
        }

        $selectedKeys = $this->getSelectedRecordKeys();

        if (empty($selectedKeys)) {
            return collect();
        }

        $table = $this->getTable();

        $query = $table->getQuery()->whereIn($table->getPrimaryKey(), $selectedKeys);

        // Aggregate columns (e.g. ->sums('items', 'line_total')) need their
        // subqueries replayed, or the computed attribute is absent and a
        // selection-scope summary plucks nothing and renders 0. Filters and sort
        // are intentionally not applied — selection is an explicit set of keys.
        //
        // No sub-row constraint is passed, which is what this path has always
        // done: with sub-row scoped filters active, a selection-scope rollup
        // therefore counts *all* children while the query-scope one counts only
        // the filtered ones. Preserved deliberately rather than "fixed" in
        // passing — whether selection should follow the sub-row filter is a
        // product question, not a refactor.
        $query = app(AggregateSubqueries::class)->apply($query, $table->getColumns());

        return $this->cachedSelectedRecords = $query->get();
    }
}
