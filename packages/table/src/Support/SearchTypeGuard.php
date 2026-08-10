<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Table;

/**
 * Refuses a search value type the table's search box can never ask for.
 *
 * `searchAs('numeric'|'date'|'code')` only says which comparisons a column can
 * answer — it does not switch comparisons on. Without
 * `->search(fn (SearchConfig $s) => $s->ranges())` the term stays one literal
 * substring, so `8866 01..08` is looked for verbatim and the table comes back
 * empty with nothing on screen to explain why.
 *
 * That is a configuration mistake with exactly one reading, so it is raised as
 * one rather than left to be discovered as a missing row: the declaration and
 * the missing switch are named together, at render time, before anything is
 * typed.
 *
 * Declaring `'text'` is exempt — it asserts no comparison and therefore needs
 * nothing turned on.
 */
final class SearchTypeGuard
{
    /**
     * @param  array<int, Column>  $columns
     *
     * @throws TableConfigurationException
     */
    public static function assert(Table $table, array $columns): void
    {
        // A table nobody can search into has no search box to configure; the
        // declaration is dormant rather than contradicted.
        if (! $table->isSearchable() || $table->getSearchConfig()->parsesRanges()) {
            return;
        }

        foreach ($columns as $column) {
            $declared = $column->getSearchValueType();

            if ($declared === null || ! $column->isSearchable() || ! $declared->supportsComparison()) {
                continue;
            }

            throw TableConfigurationException::searchValueTypeNeedsRanges(
                $column->getName(),
                $declared,
            );
        }
    }
}
