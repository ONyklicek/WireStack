<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Contracts\DehydratesState;

/**
 * Whether an emptied editable surface stores `null` rather than `''`.
 *
 * A browser has no way to submit "nothing": a cleared `<input>` arrives as an
 * empty string, and so does the empty option of a `<select>`. On a nullable
 * column that is a lie — the author meant no value, and `''` is a value. On a
 * numeric, date or enum column it is worse than a lie: Postgres and strict-mode
 * MySQL reject it, and SQLite quietly writes an empty string where a figure
 * belonged.
 *
 * The flag is configuration only. Turning `''` into `null` is the job of the
 * surface's {@see DehydratesState} implementation, because only it knows when
 * the value is on its way to the record — {@see nullifyEmptyState()} is the one
 * line it needs.
 *
 * Not every surface has to ask: a control whose empty state can only ever mean
 * "nothing" (a number input, a single select) nullifies on its own. This concern
 * is for the ones where `''` is a value an author may legitimately mean, and so
 * has to be opted out of.
 */
trait CanBeNullable
{
    protected bool $nullable = false;

    /** Store `null` instead of an empty string when the value is cleared. */
    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * An empty string on its way to the record, as `null` when the surface is
     * nullable. Every other value passes through untouched.
     */
    protected function nullifyEmptyState(mixed $state): mixed
    {
        return ($this->nullable && $state === '') ? null : $state;
    }
}
