<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

/**
 * Toggles a field between single- and multi-value mode.
 *
 * Shared by fields whose state widens to an array when several values are
 * allowed (Select, FileUpload). Each field decides what "multiple" means for
 * its own dehydration; this trait only owns the flag.
 */
trait CanBeMultiple
{
    protected bool $multiple = false;

    /** Allow selecting several values (the state becomes an array). */
    public function multiple(bool $condition = true): static
    {
        $this->multiple = $condition;

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }
}
