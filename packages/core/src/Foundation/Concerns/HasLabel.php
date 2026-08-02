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

    protected bool $labelHidden = false;

    /** Set the display label (defaults to a humanised version of the name). */
    public function label(string|Closure|null $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Hide the label visually while keeping it for accessibility.
     *
     * The label still resolves — it stays available as an `aria-label` or a
     * column heading — so this is not the same as clearing it. Use it where the
     * surrounding layout already names the value (a table repeater's column
     * header, a toolbar).
     */
    public function hiddenLabel(bool $condition = true): static
    {
        $this->labelHidden = $condition;

        return $this;
    }

    public function isLabelHidden(): bool
    {
        return $this->labelHidden;
    }

    public function getLabel(): ?string
    {
        $label = $this->evaluate($this->label);

        return $label ?? Str::headline($this->getName());
    }
}
