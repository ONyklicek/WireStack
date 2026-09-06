<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Contracts\DehydratesState;

/**
 * Whether a component's state is persisted, and what it is persisted as.
 *
 * The owner-facing half of the write path, and the canonical owner of both
 * halves of that question:
 *
 *  - `dehydrated(false)` — this key is not a column; drop it before the record
 *    is written. A password confirmation, a UI-only field that only drives a
 *    sibling, a name that is really a relation.
 *  - `dehydrateStateUsing()` — write something other than the state as typed.
 *
 * The closure is the *owner's* transform and runs after the component's own
 * {@see DehydratesState} implementation,
 * so an owner shapes the value that is about to be stored rather than racing the
 * field type over it: a FileUpload has already moved its upload, a date field has
 * already applied its storage format, and the closure sees that result.
 *
 * Both are configuration only — dropping the key and calling the closure are the
 * save handler's job, because only it knows when the write happens.
 *
 * @see FormatsStateUsing the read-path counterpart
 */
trait CanBeDehydrated
{
    protected bool|Closure $isDehydrated = true;

    protected ?Closure $dehydrateStateUsing = null;

    /**
     * Whether this field's value is written to the record on save (a bool or a
     * `$get`-aware Closure); `dehydrated(false)` keeps it out of the payload.
     */
    public function dehydrated(bool|Closure $condition = true): static
    {
        $this->isDehydrated = $condition;

        return $this;
    }

    /**
     * Transform this field's value on the way to the record; the Closure
     * receives `$state, $record` and returns the value to persist.
     */
    public function dehydrateStateUsing(?Closure $callback): static
    {
        $this->dehydrateStateUsing = $callback;

        return $this;
    }

    public function isDehydrated(): bool
    {
        return (bool) $this->evaluate($this->isDehydrated);
    }

    /**
     * Apply the owner's dehydration callback, if one was set.
     *
     * The value passes through untouched when there is none, so a caller can run
     * every field through this without asking first.
     */
    public function applyStateDehydration(mixed $state, mixed $record = null): mixed
    {
        if (! $this->dehydrateStateUsing instanceof Closure) {
            return $state;
        }

        return ($this->dehydrateStateUsing)($state, $record);
    }
}
