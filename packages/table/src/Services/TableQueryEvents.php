<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use NyonCode\WireCore\Core\Events\TableFiltered;
use NyonCode\WireCore\Core\Events\TableFiltering;
use NyonCode\WireCore\Core\Events\TableSearched;
use NyonCode\WireCore\Core\Events\TableSearching;
use NyonCode\WireTable\Table;

/**
 * The four events a table query run announces, as one pair of brackets.
 *
 * `TableSearching`/`TableSearched` and `TableFiltering`/`TableFiltered` are
 * pairs, and the point of wrapping the work rather than exposing a before and an
 * after is that a pair cannot come apart: their two halves used to sit twenty
 * lines either side of the query build, where anything returning early between
 * them would have announced a search that never finished.
 *
 * The listener contract is unchanged, including the part that looks like a bug
 * and is not: the `-ed` events carry a count of `-1`. Counting here would mean
 * running the query, and the query is built lazily — so `-1` is the documented
 * signal for "not known yet" rather than a total nobody computed.
 */
final class TableQueryEvents
{
    /**
     * Announce the run around `$build`, and hand back whatever it returned.
     *
     * Nothing is announced for an empty search or an empty filter set, which is
     * why the filters are narrowed first: a filter present in state but holding
     * null, an empty string or an empty array is a filter the user cleared, and
     * a listener told it was applied would be told wrong.
     *
     * @template T
     *
     * @param  array<string, mixed>  $filters
     * @param  callable(): T  $build
     * @return T
     */
    public function around(string $tableId, Table $table, ?string $search, array $filters, callable $build): mixed
    {
        $search = $search !== '' ? $search : null;
        $active = array_filter($filters, fn (mixed $v): bool => $v !== null && $v !== '' && $v !== []);

        if ($search !== null) {
            event(new TableSearching($tableId, $search, $this->searchableColumns($table)));
        }

        if ($active !== []) {
            event(new TableFiltering($tableId, $active));
        }

        $result = $build();

        if ($search !== null) {
            event(new TableSearched($tableId, $search, -1));
        }

        if ($active !== []) {
            event(new TableFiltered($tableId, $active, -1));
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function searchableColumns(Table $table): array
    {
        $names = [];

        foreach ($table->getColumns() as $column) {
            if ($column->isSearchable()) {
                $names[] = $column->getName();
            }
        }

        return $names;
    }
}
