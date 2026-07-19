<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Placeholder text for input fields.
 */
trait HasPlaceholder
{
    protected string|Closure|null $placeholder = null;

    /** Set the placeholder shown when the value is empty (a string or a Closure). */
    public function placeholder(string|Closure|null $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->evaluate($this->placeholder);
    }
}
