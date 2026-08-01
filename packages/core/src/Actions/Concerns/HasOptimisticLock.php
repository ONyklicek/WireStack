<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Foundation\Support\RecordVersion;

/**
 * Opt a record action into the same optimistic lock an inline edit always has.
 *
 * An inline edit is locked by default because it has no window to speak of: the
 * client sends the version it rendered with and the write is refused if the row
 * moved. A modal action has a window, and a long one — it opens, the user reads,
 * types, maybe walks away, and submits — so it is the *more* exposed of the two
 * write paths, and it was the unguarded one.
 *
 * Opt-in rather than on by default, because "the record moved" is only a problem
 * for an action that decides something from what it read. Approving an invoice
 * whose total changed underneath is a lost update; deleting a record someone
 * else renamed is not, and archiving one is not either. Refusing those would be
 * a new failure mode in return for nothing, so the author says which is which.
 *
 * The version itself is {@see RecordVersion} — the same convention and the same
 * object the cell edit compares with, so there is one answer to "has this row
 * moved", not two that can drift.
 */
trait HasOptimisticLock
{
    protected bool $optimisticLock = false;

    /**
     * Refuse this action if the record changed while its modal was open.
     */
    public function optimisticLock(bool $enabled = true): static
    {
        $this->optimisticLock = $enabled;

        return $this;
    }

    public function usesOptimisticLock(): bool
    {
        return $this->optimisticLock;
    }
}
