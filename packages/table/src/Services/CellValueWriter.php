<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Services;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\Column;

/**
 * Writes one inline-edited value onto its record.
 *
 * A column can be persisted five different ways, and the order between them is
 * the contract: an author's own callback outranks everything, the legacy
 * callback outranks the built-ins, and pivot beats relation beats a plain
 * attribute. That chain lived inside a transaction closure inside
 * WithTable::updateTableCell(), a 213-line method in a 3,500-line trait, so it
 * could not be read or exercised on its own.
 *
 * Persisting only: authorization, validation, locking and versioning stay with
 * the host, which is the only thing that can answer for them.
 */
final class CellValueWriter
{
    /**
     * Persist $value onto $record and hand back the refreshed record.
     *
     * The caller is expected to have locked the row already.
     */
    public function write(Column $column, Model $record, string $columnName, mixed $value): Model
    {
        $this->persist($column, $record, $columnName, $value);

        $record->refresh();

        return $record;
    }

    private function persist(Column $column, Model $record, string $columnName, mixed $value): void
    {
        // The author's own save callback answers for everything.
        if (method_exists($column, 'getSaveCallback') && $column->getSaveCallback()) {
            call_user_func($column->getSaveCallback(), $record, $value, $column);

            return;
        }

        if ($editableCallback = $column->getEditableCallback()) {
            call_user_func($editableCallback, $record, $value);

            return;
        }

        if ($column->isPivot()) {
            $attribute = $column->getRelationshipAttribute();
            $pivot = $record->getAttribute('pivot');

            if ($pivot instanceof Model && $attribute !== null) {
                $pivot->{$attribute} = $value;
                $pivot->save();
            }

            return;
        }

        if ($relation = $column->getRelation()) {
            $attribute = $column->getRelationshipAttribute();
            $related = data_get($record, $relation);

            if ($related instanceof Model && $attribute !== null) {
                $related->{$attribute} = $value;
                $related->save();
            }

            return;
        }

        $record->{$columnName} = $value;
        $record->save();
    }
}
