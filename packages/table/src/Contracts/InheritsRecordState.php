<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Concerns\HasInactiveRecords;
use NyonCode\WireTable\Concerns\InteractsWithRecordDisabledState;

/**
 * A column that accepts the table's row-level rule about a record.
 *
 * The seam between the two owners of "may this cell be written": the column,
 * which knows what its editor is, and the table, which knows what the *record*
 * is ({@see HasInactiveRecords}). Only the editable columns have an editor to
 * withhold, so only they implement this — a text or badge column is asked
 * nothing and given nothing.
 *
 * It exists as a contract rather than a `method_exists()` probe because the push
 * happens on every column of every table: a name typed wrongly on one column
 * would simply never be called, and the cells of a cancelled record would stay
 * writable with nothing to show for it.
 *
 * Implemented for all four editable columns by
 * {@see InteractsWithRecordDisabledState}.
 */
interface InheritsRecordState
{
    /**
     * Take the owning table's row-level disabled rule, or null to drop it.
     *
     * @param  Closure(Model): bool|null  $resolver
     */
    public function inheritDisabledState(?Closure $resolver): static;
}
