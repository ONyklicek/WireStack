<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\CanBeDisabled;

/**
 * Per-record disabled state for editable table cells.
 *
 * Distinct from the Foundation {@see CanBeDisabled}
 * concern (a column itself is never disabled): this resolves the disabled flag
 * per row, either from a static bool or a `fn (Model): bool` callback. The
 * client-side flag is cosmetic — each column's own `canEdit()` enforces it
 * server-side.
 */
trait InteractsWithRecordDisabledState
{
    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    /** Disable inline editing; a Closure receives the record per row. */
    public function disabled(bool|Closure $disabled = true): static
    {
        if ($disabled instanceof Closure) {
            $this->disabledCallback = $disabled;
        } else {
            $this->disabled = $disabled;
        }

        return $this;
    }

    public function isDisabled(Model $record): bool
    {
        if ($this->disabledCallback) {
            return ($this->disabledCallback)($record);
        }

        return $this->disabled;
    }
}
