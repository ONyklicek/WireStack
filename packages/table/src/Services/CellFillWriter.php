<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use NyonCode\WireCore\Core\Events\CellUpdating;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Exceptions\FillRequestException;
use NyonCode\WireTable\Support\CellEditOutcome;
use NyonCode\WireTable\Support\CellFill;
use NyonCode\WireTable\Support\FillResult;
use NyonCode\WireTable\Table;

/**
 * Writes one value to many records — the server half of the fill handle.
 *
 * One request, one transaction, and one locking query per column, but a
 * *per-record* write rather than a single `UPDATE … WHERE key IN (…)`. That is
 * not a missed optimisation, it is the only correct shape: a mass update skips
 * Eloquent events, casts and mutators, does not touch `updated_at` — which is
 * what the optimistic lock compares — and cannot express four of the five
 * branches of {@see CellValueWriter} (a save callback, `editableUsing()`, a
 * pivot, a relation). A vertical drag can only reach rendered rows, so the
 * record count is bounded by the page, and `Table::fillMaxRecords()` bounds a
 * forged request.
 *
 * A per-record refusal does not abort the fill. Losing one optimistic-lock race
 * must not discard the rows that did land, so refusals are returned as outcomes
 * rather than thrown; only an infrastructure failure rolls the transaction back.
 *
 * Every record is resolved through `Table::getQuery()`, so a key outside the
 * table's own scope matches nothing and is reported as missing. Skipping that is
 * how the same feature became an IDOR write in `WithSortable::reorderRows()`.
 */
final class CellFillWriter
{
    public function __construct(private readonly CellEditPipeline $pipeline) {}

    /**
     * @param  array<int, CellFill>  $fills
     *
     * @throws FillRequestException when the request exceeds the table's cap
     */
    public function write(Table $table, array $fills, string $hostId): FillResult
    {
        $requested = CellFill::countRecords($fills);
        $max = $table->getFillMaxRecords();

        if ($requested > $max) {
            throw FillRequestException::tooManyRecords($requested, $max);
        }

        $outcomes = [];

        /** @var array<int, array{CellEditOutcome, Column, string, string}> $pending */
        $pending = [];

        DB::transaction(function () use ($table, $fills, $hostId, &$outcomes, &$pending): void {
            foreach ($fills as $fill) {
                $outcomes[$fill->column] = $this->fillColumn($table, $fill, $hostId, $pending);
            }
        });

        // Outside the lock, exactly as a single edit settles after its commit.
        foreach ($pending as [$outcome, $column, $columnName, $recordKey]) {
            $this->pipeline->settle($outcome, $column, $hostId, $columnName, $recordKey);
        }

        return new FillResult($outcomes);
    }

    /**
     * @param  array<int, array{CellEditOutcome, Column, string, string}>  $pending
     * @return array<string, CellEditOutcome>
     */
    private function fillColumn(Table $table, CellFill $fill, string $hostId, array &$pending): array
    {
        $column = $table->findColumn($fill->column);

        if (! $column) {
            return $this->refuseAll($fill, CellEditOutcome::rejected(__('wire-table::messages.column_not_found')));
        }

        if (! $column->isFillable()) {
            return $this->refuseAll($fill, CellEditOutcome::rejected(__('wire-table::messages.column_not_fillable')));
        }

        if ($failure = $this->pipeline->guard($column)) {
            return $this->refuseAll($fill, $failure);
        }

        // The value is the same for every record, so the column-level stages run
        // once for the whole drag rather than once per row.
        $value = $this->pipeline->dehydrate($column, $fill->value);

        if ($failure = $this->pipeline->validateWithoutRecord($column, $fill->column, $value)) {
            return $this->refuseAll($fill, $failure);
        }

        $records = $this->lock($table, array_keys($fill->records));
        $outcomes = [];

        foreach ($fill->records as $recordKey => $clientVersion) {
            event(new CellUpdating($hostId, $fill->column, $recordKey, $value));

            $record = $records[$recordKey] ?? null;

            if (! $record) {
                $outcomes[$recordKey] = CellEditOutcome::rejected(__('wire-table::messages.record_not_found'));

                continue;
            }

            // The client's original state, not $value — see CellEditPipeline::dehydrate().
            $outcome = $this->pipeline->commit($column, $fill->column, $record, $fill->value, $clientVersion);
            $outcomes[$recordKey] = $outcome;

            if ($outcome->success) {
                $pending[] = [$outcome, $column, $fill->column, $recordKey];
            }
        }

        return $outcomes;
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<string, Model>
     */
    private function lock(Table $table, array $keys): array
    {
        $records = [];

        /** @var Model $record */
        foreach ($table->getQuery()->whereIn($table->getPrimaryKey(), $keys)->lockForUpdate()->get() as $record) {
            $records[(string) $record->getKey()] = $record;
        }

        return $records;
    }

    /**
     * A column-level refusal applies to every record the entry named. The outcome
     * is immutable, so one instance answers for all of them.
     *
     * @return array<string, CellEditOutcome>
     */
    private function refuseAll(CellFill $fill, CellEditOutcome $outcome): array
    {
        return array_fill_keys(array_keys($fill->records), $outcome);
    }
}
