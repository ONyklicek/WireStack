<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Plugin\Hooks;

use NyonCode\WireCore\Core\Plugin\Contracts\HasHookTarget;
use NyonCode\WireCore\Core\Plugin\HookTarget;

/**
 * Typed payload for the 'cell.updating' hook.
 *
 * An inline cell edit is a save, and it was the only write path in the table
 * with nothing that could change it: `CellUpdating` and `CellUpdated` are Laravel
 * events, which by the rule this repository draws between the two mechanisms may
 * watch a value change and never alter it. A form save had `form.saving`; the
 * cell beside it had neither.
 *
 * Dispatched at the one point both writes funnel through — the inline editor and
 * the fill handle share `CellEditPipeline::commit()` — and **after** the column's
 * own permission check, the optimistic-lock check and its validation. A callback
 * therefore narrows what is written and cannot widen past a guard the column
 * declared, which is the same ordering `search.querying` follows around its
 * policy check.
 *
 * Two things are modifiable, and the second is the reason this is a hook rather
 * than a second event:
 *
 * ```php
 * $payload->value = strtoupper((string) $payload->value);   // change what is written
 * $payload->refusal = 'Locked while the invoice is approved.';  // refuse it outright
 * ```
 *
 * A refusal reaches the browser as the cell's own error message — the same shape
 * a failed validation produces — and nothing is written.
 *
 * Typed only.
 */
final class CellUpdatingPayload implements HasHookTarget
{
    /**
     * @param  object  $column  The column being edited
     * @param  string  $columnName  The attribute the value lands on
     * @param  object  $record  The record, already locked, not yet written
     * @param  mixed  $value  The dehydrated value about to be written (modifiable)
     * @param  mixed  $oldValue  What the record holds now
     * @param  string|null  $refusal  Set to a message to stop the write (modifiable)
     * @param  HookTarget|null  $target  Which surface this came from, for scoped callbacks
     */
    public function __construct(
        public readonly object $column,
        public readonly string $columnName,
        public readonly object $record,
        public mixed $value,
        public readonly mixed $oldValue = null,
        public ?string $refusal = null,
        public readonly ?HookTarget $target = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'column' => $this->column,
            'columnName' => $this->columnName,
            'record' => $this->record,
            'value' => $this->value,
            'oldValue' => $this->oldValue,
            'refusal' => $this->refusal,
        ];
    }

    public function hookTarget(): ?HookTarget
    {
        return $this->target;
    }
}
