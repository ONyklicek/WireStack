<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Concerns;

/**
 * Autofocus support for form fields.
 */
trait CanBeAutofocused
{
    protected bool $autofocus = false;

    /** Focus this field automatically when the form renders. */
    public function autofocus(bool $condition = true): static
    {
        $this->autofocus = $condition;

        return $this;
    }

    public function hasAutofocus(): bool
    {
        return $this->autofocus;
    }
}
