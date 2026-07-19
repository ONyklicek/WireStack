<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Helper text displayed below form fields.
 */
trait HasHelperText
{
    protected string|Closure|null $helperText = null;

    /** Set helper text shown beneath the component. */
    public function helperText(string|Closure|null $text): static
    {
        $this->helperText = $text;

        return $this;
    }

    public function getHelperText(): ?string
    {
        return $this->evaluate($this->helperText);
    }
}
