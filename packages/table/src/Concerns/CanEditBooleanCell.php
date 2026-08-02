<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Capabilities\Capability;

/**
 * Shared wiring for a column whose cell writes a boolean straight to the record
 * (ToggleColumn, CheckboxColumn).
 *
 * It exists for the server-side guard: the client-side `disabled` state is only
 * cosmetic — a forged request can still reach `WithTable::updateTableCell()` —
 * so every boolean cell must also reject a per-record disabled write here.
 * Owning it once means a new boolean cell cannot ship without the guard.
 */
trait CanEditBooleanCell
{
    use HasRecordVersion;
    use InteractsWithRecordDisabledState;

    /**
     * Server-side edit guard consulted by WithTable::updateTableCell().
     *
     * Column-level permissions are enforced separately by updateTableCell before
     * the write; this answers only for the per-record disabled state.
     */
    public function canEdit(Model $record): bool
    {
        return ! $this->isDisabled($record);
    }

    /** Mark the column editable. */
    protected function markEditable(): void
    {
        $this->capabilities = $this->capabilities->add(Capability::Editable);
    }
}
