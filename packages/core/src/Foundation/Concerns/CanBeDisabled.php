<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Being disabled.
 *
 * Split out of {@see HasVisibility}, which bundled it: "can this be seen" and
 * "can this be interacted with" are two capabilities, and plenty of surfaces
 * have the first without the second — a table column can be hidden, but it can
 * never be disabled. Compose both where both apply (fields, layouts).
 */
trait CanBeDisabled
{
    protected bool|Closure $isDisabled = false;

    /** Disable the component (a bool or a `$get`-aware Closure). On a table cell this is cosmetic; server-side `canEdit()` enforces it. */
    public function disabled(bool|Closure $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

    public function isDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDisabled);
    }
}
