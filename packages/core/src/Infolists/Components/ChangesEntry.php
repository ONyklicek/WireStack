<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Infolists\Components;

use NyonCode\WireCore\Foundation\ValueObjects\ChangeSet;

/**
 * Changes entry — a before-and-after diff as one table, a row per field.
 *
 * The shape a reader of an audit entry, a revision or a sync report is actually
 * looking for: what moved, from what, to what, with the old side and the new
 * side beside each other and coloured so a scan finds them. A repeated card per
 * field says the same thing and repeats its three headings once per row, which
 * is the work the diff exists to have already done.
 *
 * **State is a diff, not a record.** It takes either the `{old, new}` pairs an
 * audit entry produces, or rows that are already `{field, before, after}` —
 * whichever the caller has. Both go through {@see ChangeSet}, so what a boolean
 * or a JSON column looks like is decided in one place rather than in a view.
 *
 * It knows nothing about auditing, deliberately: `Infolists` and `Audit` are
 * both L2 and may not see each other (ADR 0025), and a diff of two arrays is not
 * an audit concept anyway.
 */
class ChangesEntry extends Entry
{
    protected bool $dense = false;

    /**
     * Draw it a size smaller.
     *
     * For a diff inside something else — a slide-over, a timeline entry — where
     * the table is supporting evidence rather than the subject of the page.
     */
    public function dense(bool $dense = true): static
    {
        $this->dense = $dense;

        return $this;
    }

    public function isDense(): bool
    {
        return $this->dense;
    }

    /**
     * The rows to draw, whichever shape the state arrived in.
     *
     * A `{field, before, after}` list passes through; anything else is read as
     * the `{old, new}` map an audit diff produces. Guessing between the two is
     * better than a second method for it: the caller has one of them, and both
     * mean the same thing.
     *
     * @return array<int, array{field: string, before: string|null, after: string|null}>
     */
    public function getRows(): array
    {
        $state = $this->getState();

        if (! is_array($state) || $state === []) {
            return [];
        }

        $first = reset($state);

        if (is_array($first) && array_key_exists('field', $first)) {
            return array_values($state);
        }

        return ChangeSet::fromDiff($state)->rows;
    }

    protected function viewName(): string
    {
        return 'wire-core::infolists.entries.changes';
    }
}
