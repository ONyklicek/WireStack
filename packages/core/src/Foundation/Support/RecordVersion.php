<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The optimistic-locking convention shared by every inline-edit surface.
 *
 * A record's "version" is its `updated_at` timestamp as a string. The client
 * holds the version it read, sends it back with the edit, and the write is
 * rejected if the record moved underneath it.
 *
 * Small, but it is domain knowledge rather than a helper, and it was written out
 * twice — once in the table's updateTableCell(), once in the panel's
 * updatePanelEntry() — plus eight hand-rolled copies of the stamp itself. Three
 * details have to agree across both, and none of them are obvious:
 *
 *  - a model with no `updated_at` has no version, and is therefore unguarded;
 *  - `'0'` is the client's "I never had a version" sentinel, not a timestamp —
 *    treating it as one would reject every first edit;
 *  - the comparison is on the string, because that is what crosses the wire.
 */
final class RecordVersion
{
    /** The version stamp a client should hold for this record, if it has one. */
    public function stamp(Model $record): ?string
    {
        // Via getUpdatedAtColumn() rather than ->updated_at: a model is free to
        // name the column something else, and the old hand-rolled copies read the
        // literal attribute, so such a record silently had no version at all —
        // which left it unguarded rather than merely unstamped.
        $updatedAt = $record->getAttribute($record->getUpdatedAtColumn());

        return $updatedAt instanceof DateTimeInterface
            ? (string) $updatedAt->getTimestamp()
            : null;
    }

    /**
     * Whether the record moved since the client read the value it is editing.
     *
     * False when the client sent no version, when it sent the `'0'` sentinel, or
     * when the model is not timestamped — in all three there is nothing to
     * compare and the edit proceeds.
     */
    public function conflicts(Model $record, ?string $clientVersion): bool
    {
        if ($clientVersion === null || $clientVersion === '0') {
            return false;
        }

        $current = $this->stamp($record);

        return $current !== null && $current !== $clientVersion;
    }
}
