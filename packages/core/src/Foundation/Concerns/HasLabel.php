<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use Illuminate\Support\Str;

/**
 * Provides a label property with auto-generation from name.
 */
trait HasLabel
{
    protected string|Closure|null $label = null;

    /** Set the display label (defaults to a humanised version of the name). */
    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): ?string
    {
        $label = $this->evaluate($this->label);

        return $label ?? Str::headline($this->getName());
    }
}
