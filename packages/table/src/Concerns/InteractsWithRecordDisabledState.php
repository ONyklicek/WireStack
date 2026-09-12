<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Foundation\Concerns\CanBeDisabled;
use NyonCode\WireTable\Table;

/**
 * Per-record disabled state for editable table cells.
 *
 * Distinct from the Foundation {@see CanBeDisabled}
 * concern (a column itself is never disabled): this resolves the disabled flag
 * per row, either from a static bool or a `fn (Model): bool` callback. The
 * client-side flag is cosmetic — each column's own `canEdit()` enforces it
 * server-side.
 *
 * **Two owners, deliberately kept apart.** The author's own `disabled()` is one
 * source; the table's row-level state ({@see HasInactiveRecords})
 * is the other, pushed in through {@see inheritDisabledState()}. They are held
 * in separate slots rather than merged at call time because either may be
 * declared after the other, and a column whose `disabled()` overwrote the
 * table's rule would silently reopen a record the table has locked. Both are
 * consulted, and either one disables the cell.
 */
trait InteractsWithRecordDisabledState
{
    protected bool $disabled = false;

    protected ?Closure $disabledCallback = null;

    /** The owning table's row-level rule, never the author's own. */
    protected ?Closure $inheritedDisabledCallback = null;

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

    /**
     * Take the owning table's row-level disabled rule (or null to drop it).
     *
     * Pushed by {@see Table::columnSet()}, not by the author
     * — a column declares its own cells' rules, a table declares the row's.
     */
    public function inheritDisabledState(?Closure $resolver): static
    {
        $this->inheritedDisabledCallback = $resolver;

        return $this;
    }

    public function isDisabled(Model $record): bool
    {
        if ($this->inheritedDisabledCallback !== null && ($this->inheritedDisabledCallback)($record)) {
            return true;
        }

        if ($this->disabledCallback) {
            return ($this->disabledCallback)($record);
        }

        return $this->disabled;
    }
}
