<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

/**
 * A host that decides which of a table's columns are on screen.
 *
 * One method, because it is one question. A column can be hidden by the user
 * through the column-toggle menu, by a responsive rule, or by never having been
 * visible — and the render layer does not care which: it asks whether to render
 * the column and gets an answer.
 *
 * This exists so the render layer can say what it needs from its host instead
 * of taking the whole component as `mixed`. Before it, a plan slice could only
 * be exercised by building a Livewire component, which is what
 * `v2.1-monolith-split-implementation.md`'s DoD 2 records as unmet.
 */
interface ShowsTableColumns
{
    public function isColumnVisible(string $column): bool;
}
