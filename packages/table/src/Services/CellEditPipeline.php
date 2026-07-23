<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Events\CellUpdated;
use NyonCode\WireCore\Core\Validation\ValidationPipeline;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;
use NyonCode\WireCore\Foundation\Contracts\HydratesState;
use NyonCode\WireCore\Foundation\Support\RecordVersion;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Support\CellEditOutcome;

/**
 * The stages of one inline-cell edit: guard, dehydrate, validate, commit, settle.
 *
 * This is the body of `WithTable::updateTableCell()` — a 166-line method in a
 * 2,200-line trait — lifted out so it can be read, tested and reused. It is
 * split into named stages rather than one `run()` because the two callers need
 * different granularity: a single cell edit runs every stage once, while a fill
 * over N rows writes one value to many records and must run the column-level
 * stages (guard, the record-less dehydrate and validation) exactly once, then
 * {@see commit()} and {@see settle()} per record.
 *
 * Deliberately not owned here: resolving and locking the record, and the
 * transaction. The caller decides those, exactly as {@see CellValueWriter}
 * already assumes ("the caller is expected to have locked the row already") —
 * one row under `lockForUpdate()` for an edit, a set under one transaction for a
 * fill.
 */
final class CellEditPipeline
{
    public function __construct(
        private readonly CellValueWriter $writer,
        private readonly RecordVersion $version,
        private readonly ValidationPipeline $validator,
    ) {}

    /**
     * Column-level refusals, checked before the value is touched at all.
     *
     * Runs before {@see dehydrate()} on purpose: dehydration can invoke an
     * author's `beforeSave()` closure, and a column the user may not write must
     * not run one.
     *
     * Returns null when the edit may proceed.
     */
    public function guard(Column $column): ?CellEditOutcome
    {
        if (! $column->isEditable()) {
            return CellEditOutcome::rejected(__('wire-table::messages.column_not_editable'));
        }

        // The canonical fail-CLOSED authorization owner (HasAuthorization), a Gate
        // check that works with Laravel policies and Spatie alike. It returns true
        // when no permission is configured, so unrestricted columns are unaffected.
        if (! $column->isAuthorized()) {
            return CellEditOutcome::rejected(__('wire-table::messages.no_permission_view'));
        }

        return null;
    }

    /**
     * Apply the column's write-path transform (ADR 0021).
     *
     * Always call this on the client's original state, never on its own output:
     * `dehydrateState()` is a pure function of its arguments, which does not make
     * it idempotent under self-composition — feeding it back would apply a
     * `beforeSave()` closure twice.
     */
    public function dehydrate(Column $column, mixed $state, ?Model $record = null): mixed
    {
        return $column instanceof DehydratesState
            ? $column->dehydrateState($state, $record)
            : $state;
    }

    /**
     * The basic rules, checked before a record is fetched or locked.
     *
     * Returns null when the value passes, or when the column declares no
     * record-less rules.
     */
    public function validateWithoutRecord(Column $column, string $columnName, mixed $value): ?CellEditOutcome
    {
        $rules = $column->getEditableRules(null);

        if ($rules === []) {
            return null;
        }

        $result = $this->validator->validate(
            [$columnName => $value],
            [$columnName => $rules],
        );

        if (! $result->failed()) {
            return null;
        }

        $errors = $result->getError($columnName) ?? [];

        return CellEditOutcome::invalid(
            $errors[0] ?? __('wire-table::messages.validation_failed'),
            $errors,
        );
    }

    /**
     * The record-aware half: per-record permission, optimistic lock, the
     * record-aware dehydrate and validation, then the write.
     *
     * `$state` is the client's original state, not the output of
     * {@see dehydrate()} — see that method.
     *
     * The caller must already hold a transaction and a lock on `$record`.
     */
    public function commit(
        Column $column,
        string $columnName,
        Model $record,
        mixed $state,
        ?string $clientVersion,
    ): CellEditOutcome {
        $oldValue = $record->{$columnName};

        // The client-side disabled state is only cosmetic — a forged request could
        // still reach the host — so a per-record disabled cell is rejected again
        // here. Only the concrete editable columns declare canEdit().
        if (method_exists($column, 'canEdit') && ! $column->canEdit($record)) {
            return CellEditOutcome::rejected(__('wire-table::messages.no_permission_edit'));
        }

        if ($this->version->conflicts($record, $clientVersion)) {
            $currentValue = $record->{$columnName};

            if ($column instanceof HydratesState) {
                $currentValue = $column->hydrateState($currentValue, $record);
            }

            return CellEditOutcome::conflicted(
                __('wire-table::messages.record_conflict'),
                $currentValue,
                $this->version->stamp($record),
            );
        }

        $value = $this->dehydrate($column, $state, $record);

        if (method_exists($column, 'validate')) {
            $validation = $column->validate($value, $record);

            if (! $validation['valid']) {
                return CellEditOutcome::invalid(
                    $validation['errors'][0] ?? __('wire-table::messages.validation_failed'),
                    $validation['errors'],
                );
            }
        }

        $record = $this->writer->write($column, $record, $columnName, $value);

        return CellEditOutcome::saved(
            $record,
            $this->version->stamp($record),
            $value,
            $oldValue,
        );
    }

    /**
     * Side effects that must happen after the lock is released: the column's
     * afterStateUpdated callback and the CellUpdated event.
     *
     * A no-op for anything but a successful commit. `$recordKey` is the key the
     * client sent, so the event carries the same identity the caller was given.
     */
    public function settle(
        CellEditOutcome $outcome,
        Column $column,
        string $hostId,
        string $columnName,
        mixed $recordKey,
    ): void {
        if (! $outcome->success || $outcome->record === null) {
            return;
        }

        if (method_exists($column, 'getAfterStateUpdatedCallback') && $column->getAfterStateUpdatedCallback()) {
            call_user_func($column->getAfterStateUpdatedCallback(), $outcome->record, $outcome->savedValue);
        }

        event(new CellUpdated($hostId, $columnName, $recordKey, $outcome->oldValue, $outcome->savedValue));
    }
}
