<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Contracts\InheritsRecordState;
use NyonCode\WireTable\Support\ColumnSet;
use NyonCode\WireTable\Support\InactiveRow;
use NyonCode\WireTable\Table;

/**
 * The table's side of the inactive-record state: which records are inactive,
 * and where that answer is needed.
 *
 * Configuration and cascade only — {@see InactiveRow} owns the vocabulary, the
 * defaults and the classes; this owns where a table keeps its predicate and how
 * that predicate reaches the three places that must agree about it:
 *
 *  - **the columns**, so an editor is not offered and, more importantly, a write
 *    is refused. The predicate is pushed into every editable column's own
 *    per-record disabled state ({@see InteractsWithRecordDisabledState}), which
 *    means the existing server-side gate — `Column::canEdit()` in
 *    `Services\CellEditPipeline::commit()` — enforces it for a single cell edit
 *    and a fill-handle drag alike, with no second rule to keep in step;
 *  - **the row**, through the class strings the row and the stacked card compose
 *    ({@see Table::getRowClasses()}, {@see StacksOnMobile::getRowCardClasses()});
 *  - **the host**, for the two locks that are not about editing — the selection
 *    checkbox and the row's actions.
 *
 * The push happens at {@see Table::columnSet()} rather than in `rowInactive()`
 * itself, because the two declarations may arrive in either order: a table that
 * says `->rowInactive(...)` before `->columns([...])` must lock the same cells
 * as one that says it after.
 *
 * @phpstan-require-extends Table
 */
trait HasInactiveRecords
{
    /** Null = the table never declared the state; a bool/Closure = it did. */
    protected bool|Closure|null $inactiveWhen = null;

    /** Resolved lazily from `config('wire-table.defaults.inactive_rows')`. */
    protected ?InactiveRow $inactiveRow = null;

    /** Set when the predicate or the columns change; cleared once pushed. */
    protected bool $inactiveStatePending = false;

    /**
     * Mark records inactive: still listed, still readable, no longer writable.
     *
     * The first argument decides *which* rows (a Closure receives the record);
     * the optional second shapes what the state does, in place:
     *
     *   $table->rowInactive(fn (Order $o) => $o->status === 'cancelled');
     *
     *   $table->rowInactive(
     *       fn (Order $o) => $o->status === 'cancelled',
     *       fn (InactiveRow $row) => $row
     *           ->strikethrough()          // the values were voided
     *           ->color('danger')          // tint the whole row
     *           ->selectable(false),       // and keep it out of bulk actions
     *   );
     *
     * Inline editing is locked by default and the lock is enforced server-side;
     * row actions, the checkbox and a record click stay live unless the
     * configurator says otherwise. See {@see InactiveRow} for each switch.
     *
     * The closure receives this table's {@see InactiveRow} (seeded from the
     * config default) and configures it in place; its return value is ignored,
     * so both a fluent chain and a multi-line body work.
     *
     * @param  bool|Closure(Model): bool  $when
     * @param  Closure(InactiveRow): mixed|InactiveRow|null  $configure
     */
    public function rowInactive(bool|Closure $when = true, Closure|InactiveRow|null $configure = null): static
    {
        $this->inactiveWhen = $when;
        $this->inactiveStatePending = true;

        if ($configure instanceof InactiveRow) {
            $this->inactiveRow = $configure;

            return $this;
        }

        if ($configure !== null) {
            $configure($this->getInactiveRow());
        }

        return $this;
    }

    /**
     * This table's inactive-row configuration, seeded once from the project
     * default. Always answers — a table that never declared the state simply has
     * nothing that {@see isRecordInactive()} returns true for.
     */
    public function getInactiveRow(): InactiveRow
    {
        /** @var array<string, bool|string|null>|null $default */
        $default = config('wire-table.defaults.inactive_rows');

        return $this->inactiveRow ??= InactiveRow::fromConfig($default);
    }

    /**
     * Whether this table declares the state at all — the cheap check every
     * render path takes before resolving anything per record.
     */
    public function hasInactiveRecords(): bool
    {
        return $this->inactiveWhen !== null && $this->inactiveWhen !== false;
    }

    /** Whether this record is inactive. False for every record of a table that never said. */
    public function isRecordInactive(?Model $record): bool
    {
        if ($record === null || ! $this->hasInactiveRecords()) {
            return false;
        }

        return $this->inactiveWhen instanceof Closure
            ? (bool) ($this->inactiveWhen)($record)
            : true;
    }

    /** Whether this record's checkbox is inert and its key refused by the selection. */
    public function isRecordSelectionLocked(Model $record): bool
    {
        return ! $this->getInactiveRow()->allowsSelection() && $this->isRecordInactive($record);
    }

    /** Whether this record's actions are inert, unbuilt in the context menu, and refused by the host. */
    public function isRecordActionLocked(Model $record): bool
    {
        return ! $this->getInactiveRow()->allowsActions() && $this->isRecordInactive($record);
    }

    /** The inactive row's share of the `<tr>` class string (empty for an active one). */
    public function getInactiveRowClasses(?Model $record): string
    {
        return $record !== null && $this->isRecordInactive($record)
            ? $this->getInactiveRow()->rowClasses()
            : '';
    }

    /** The same for a stacked card. */
    public function getInactiveCardClasses(?Model $record): string
    {
        return $record !== null && $this->isRecordInactive($record)
            ? $this->getInactiveRow()->cardClasses()
            : '';
    }

    /**
     * The row's state attributes, filled into the `<tr>`'s one state slot.
     *
     * A whole attribute pair or an empty string, composed in PHP for the same
     * reason `$partialAnchor` next to it is: the row skeleton carries the slot
     * only for a table that declares the state, so an ordinary table pays no
     * byte for it. `aria-disabled` is the half assistive technology reads;
     * `data-inactive` is the hook an application styles or a driver asserts by.
     *
     * Takes the resolved flag rather than the record: the renderer has already
     * asked the predicate once for this row and decides three other things from
     * the same answer.
     */
    public function getRowStateAttributes(bool $isInactive): string
    {
        return $isInactive ? ' aria-disabled="true" data-inactive="true"' : '';
    }

    /**
     * Hand every editable column the table's row-level rule.
     *
     * Called from {@see Table::columnSet()} on first use after either half was
     * declared, which is what makes the declaration order irrelevant. A column
     * that cannot be edited has no such state to inherit and is skipped.
     */
    protected function shareInactiveStateWithColumns(ColumnSet $set): void
    {
        if (! $this->inactiveStatePending) {
            return;
        }

        $this->inactiveStatePending = false;

        $resolver = $this->hasInactiveRecords() && ! $this->getInactiveRow()->allowsEditing()
            ? fn (Model $record): bool => $this->isRecordInactive($record)
            : null;

        foreach ($set->all() as $column) {
            if ($column instanceof InheritsRecordState) {
                $column->inheritDisabledState($resolver);
            }
        }
    }
}
