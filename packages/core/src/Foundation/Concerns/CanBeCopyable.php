<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Click-to-copy capability for a value-displaying surface.
 *
 * Owns the `$copyable` flag with its fluent toggle and predicate. Surfaces that
 * also carry a "copied" confirmation message (e.g. the table Column, whose
 * default is a package-specific translation key) keep that richer setter local
 * — a shared Foundation concern must not hardcode a downstream translation.
 */
trait CanBeCopyable
{
    protected bool $copyable = false;

    /** Add a click-to-copy button for the value. */
    public function copyable(bool $condition = true): static
    {
        $this->copyable = $condition;

        return $this;
    }

    public function isCopyable(): bool
    {
        return $this->copyable;
    }
}
