<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use NyonCode\WireTable\Services\AggregateSubqueries;
use NyonCode\WireTable\Table;

/**
 * Selecting records for bulk actions.
 *
 * A host concern: these are the endpoints the table's checkboxes call, so they
 * stay on the Livewire component — this splits the section out of the 3,500-line
 * WithTable rather than moving it away from its host.
 *
 * Selection has two shapes, and the difference is what makes "select everything"
 * possible at all:
 *
 *  - **keys** — `selection.records` is the selection, an explicit set deliberately
 *    unaffected by the table's filters and sort.
 *  - **all** — everything the *current filter* matches is selected, and the same
 *    list holds the exclusions instead. Unticking one row out of 128k is one
 *    entry, not 127 999, and no list of keys ever has to reach the browser.
 *
 * Because "all" is defined by the filter, {@see resetSelectionScope()} drops back
 * to keys the moment the filter or search changes — otherwise "everything" would
 * silently come to mean a different set of rows than the one the user saw.
 *
 * The materialised set is memoised per request because a selection-scope summary,
 * a grand total and a bulk modal can each ask for it within one render; the memo
 * is dropped whenever the selection mutates or the table cache is invalidated.
 */
trait CanSelectRecords
{
    /** @var Collection|null Memoized selected records — cleared when the selection mutates */
    protected ?Collection $cachedSelectedRecords = null;

    /** Memoized count of the rows the current filter matches ("all" mode). */
    protected ?int $cachedMatchingCount = null;

    /**
     * Toggle record selection.
     *
     * In "all" mode the list holds exclusions, so the same toggle reads as
     * "exclude this row" without any branching here.
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
     * Select every record on the current page, keeping selections made on other
     * pages.
     *
     * The union is the point: this used to overwrite the set, so selecting page
     * one and then page two silently discarded page one.
     */
    public function selectAllRecords(): void
    {
        $selected = $this->selectsAllMatching()
            ? []
            : $this->tableState->get('selection.records', []);

        foreach ($this->getPageRecordKeys() as $key) {
            if (! in_array($key, $selected, true)) {
                $selected[] = $key;
            }
        }

        $this->tableState->set('selection.mode', 'keys');
        $this->tableState->set('selection.records', $selected);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Drop the current page from the selection, leaving other pages alone.
     */
    public function deselectPageRecords(): void
    {
        $pageKeys = $this->getPageRecordKeys();

        $selected = $this->selectsAllMatching()
            ? []
            : array_values(array_diff($this->tableState->get('selection.records', []), $pageKeys));

        $this->tableState->set('selection.mode', 'keys');
        $this->tableState->set('selection.records', $selected);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Select every record the current filter matches, including rows on pages
     * the user has never opened.
     *
     * Stored as a mode rather than a list: the point of this control is the case
     * where the list would be far too long to hold.
     */
    public function selectAllMatchingRecords(): void
    {
        $this->tableState->set('selection.mode', 'all');
        $this->tableState->set('selection.records', []);
        $this->cachedSelectedRecords = null;
    }

    /**
     * Whether the selection currently means "everything the filter matches".
     */
    public function selectsAllMatching(): bool
    {
        return $this->tableState->get('selection.mode') === 'all';
    }

    /**
     * Narrow an "all matching" selection back to the rows on this page.
     */
    public function selectOnlyPageRecords(): void
    {
        $this->tableState->set('selection.mode', 'keys');
        $this->tableState->set('selection.records', $this->getPageRecordKeys());
        $this->cachedSelectedRecords = null;
    }

    /**
     * Return to an explicit selection when the set "everything" refers to could
     * have changed underneath it.
     *
     * Called from the filter and search paths: a filter change is exactly the
     * moment "all 128 matching" would quietly start meaning different rows — and
     * that is how a bulk delete hits records the user never saw.
     */
    public function resetSelectionScope(): void
    {
        if (! $this->selectsAllMatching()) {
            return;
        }

        $this->tableState->set('selection.mode', 'keys');
        $this->tableState->set('selection.records', []);
        $this->cachedSelectedRecords = null;
        $this->cachedMatchingCount = null;
    }

    /**
     * Check if record is selected.
     */
    public function isRecordSelected(string $key): bool
    {
        $listed = in_array($key, $this->tableState->get('selection.records', []), true);

        return $this->selectsAllMatching() ? ! $listed : $listed;
    }

    /**
     * Get selected records count.
     *
     * In "all" mode this is a COUNT over the filtered query minus the exclusions
     * — never a materialised set.
     */
    public function getSelectedRecordsCount(): int
    {
        $listed = count($this->tableState->get('selection.records', []));

        if (! $this->selectsAllMatching()) {
            return $listed;
        }

        return max(0, $this->getMatchingRecordsCount() - $listed);
    }

    /**
     * How many rows the current filter matches, memoized per request.
     */
    public function getMatchingRecordsCount(): int
    {
        return $this->cachedMatchingCount ??= $this->buildTableQuery()->toBase()->getCountForPagination();
    }

    /**
     * Check if some (but not all) visible records are selected
     */
    public function areSomeVisibleSelected(): bool
    {
        if ($this->getSelectedRecordsCount() === 0) {
            return false;
        }

        return ! $this->areAllVisibleSelected();
    }

    /**
     * Check if all visible records are selected
     */
    public function areAllVisibleSelected(): bool
    {
        $pageKeys = $this->getPageRecordKeys();

        if ($pageKeys === []) {
            return false;
        }

        foreach ($pageKeys as $key) {
            if (! $this->isRecordSelected($key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get array of selected record keys.
     *
     * Meaningful in "keys" mode only — in "all" mode the selection is a query, so
     * ask {@see selectedRecordsQuery()} or {@see eachSelectedRecord()} instead of
     * expanding it into keys.
     *
     * @return array<int, string>
     */
    public function getSelectedRecordKeys(): array
    {
        return $this->selectsAllMatching()
            ? []
            : $this->tableState->get('selection.records', []);
    }

    /**
     * The record keys rendered on the current page.
     *
     * @return array<int, string>
     */
    public function getPageRecordKeys(): array
    {
        $primaryKey = $this->getTable()->getPrimaryKey();
        $keys = [];

        // Iterated rather than collect()ed: collect() on a paginator wraps its
        // array *representation* (data, total, links…), not its records.
        foreach ($this->getTableRecords() as $record) {
            $keys[] = (string) $record->{$primaryKey};
        }

        return $keys;
    }

    /**
     * Deselect all records
     */
    public function deselectAllRecords(): void
    {
        $this->tableState->set('selection.mode', 'keys');
        $this->tableState->set('selection.records', []);
        $this->cachedSelectedRecords = null;
    }

    /**
     * The selection as a query — the one place that knows how each mode narrows
     * the rows, so callers never branch on the mode themselves.
     *
     * @return Builder<Model>
     */
    public function selectedRecordsQuery(): Builder
    {
        $table = $this->getTable();
        $listed = $this->tableState->get('selection.records', []);

        if ($this->selectsAllMatching()) {
            // "Everything the filter matches" — the filtered query, minus the
            // rows the user unticked.
            $query = $this->buildTableQuery();

            if ($listed !== []) {
                // Qualify the key: buildTableQuery() may carry a belongs-to join
                // (sorting/filtering by a relation column), and a joined table
                // commonly has its own `id`, so a bare column would be ambiguous.
                $query->whereNotIn($query->getModel()->qualifyColumn($table->getPrimaryKey()), $listed);
            }

            return $query;
        }

        $query = $table->getQuery()->whereIn($table->getPrimaryKey(), $listed);

        // Aggregate columns (e.g. ->sums('items', 'line_total')) need their
        // subqueries replayed, or the computed attribute is absent and a
        // selection-scope summary plucks nothing and renders 0. Filters and sort
        // are intentionally not applied — a keyed selection is an explicit set.
        //
        // No sub-row constraint is passed, which is what this path has always
        // done: with sub-row scoped filters active, a selection-scope rollup
        // therefore counts *all* children while the query-scope one counts only
        // the filtered ones. Preserved deliberately rather than "fixed" in
        // passing — whether selection should follow the sub-row filter is a
        // product question, not a refactor.
        return app(AggregateSubqueries::class)->apply($query, $table->getColumns());
    }

    /**
     * Walk the selection in chunks, without ever holding it all in memory.
     *
     * This is what a bulk action over an "all matching" selection should use:
     * `getSelectedRecords()` on 128k rows is an out-of-memory error, not a
     * feature.
     *
     * @param  callable(Model): mixed  $callback
     */
    public function eachSelectedRecord(callable $callback, int $chunk = 500): void
    {
        if ($this->getSelectedRecordsCount() === 0) {
            return;
        }

        $this->selectedRecordsQuery()
            ->chunkById($chunk, function (Collection $records) use ($callback): void {
                foreach ($records as $record) {
                    $callback($record);
                }
            });
    }

    /**
     * Get Collection of selected records (fetched from database).
     *
     * Memoized per request — selection-scope summaries, grand totals, and bulk
     * modals may all ask for the set within one render. The memo is cleared
     * whenever the selection mutates or the table cache is invalidated.
     *
     * Capped by {@see Table::bulkMaxRecords()}: an "all
     * matching" selection can be arbitrarily large, and silently materialising it
     * is how a page dies. Over the cap this returns an empty collection —
     * {@see hasTooManySelectedRecords()} is what the callers check to say so out
     * loud instead of acting on a truncated set.
     */
    public function getSelectedRecords(): Collection
    {
        if ($this->cachedSelectedRecords !== null) {
            return $this->cachedSelectedRecords;
        }

        if ($this->getSelectedRecordsCount() === 0 || $this->hasTooManySelectedRecords()) {
            return collect();
        }

        return $this->cachedSelectedRecords = $this->selectedRecordsQuery()->get();
    }

    /**
     * Whether the selection is larger than one request may materialise.
     */
    public function hasTooManySelectedRecords(): bool
    {
        $cap = $this->getTable()->getBulkMaxRecords();

        return $cap !== null && $this->getSelectedRecordsCount() > $cap;
    }
}
