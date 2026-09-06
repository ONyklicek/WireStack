<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * The owner's escape hatch for shaping a resolved value before it is used.
 *
 * The closure sibling of {@see FormatsState}, which owns the declarative
 * vocabulary (`money()`, `numeric()`, `date()`). Where that one covers the cases
 * worth naming, this one covers the rest — and it is one capability with three
 * hosts, so it has one owner:
 *
 *  - a table column formats a cell before it is rendered,
 *  - an infolist entry formats an entry before it is displayed,
 *  - a form field formats a stored value into the state its input binds to
 *    (the read path; {@see CanBeDehydrated} is the write path back).
 *
 * All three receive `$state, $record` and return the value to use, so the same
 * closure can be moved between surfaces unchanged.
 */
trait FormatsStateUsing
{
    protected ?Closure $formatStateUsing = null;

    /** Transform the resolved state before it is used; the Closure receives `$state, $record`. */
    public function formatStateUsing(Closure $callback): static
    {
        $this->formatStateUsing = $callback;

        return $this;
    }

    /**
     * Apply the owner's formatting callback, if one was set.
     *
     * The value passes through untouched when there is none, so a caller can run
     * every value through this without asking first. Public because the host is
     * not always the surface: a form field is formatted by the form runtime as
     * it fills state, the way its write-path counterpart
     * {@see CanBeDehydrated::applyStateDehydration()} is applied by the save
     * handler.
     */
    public function applyStateFormatter(mixed $state, mixed $record = null): mixed
    {
        if (! $this->formatStateUsing instanceof Closure) {
            return $state;
        }

        return ($this->formatStateUsing)($state, $record);
    }
}
