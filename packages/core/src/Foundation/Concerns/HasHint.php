<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Hint text/icon displayed alongside the field label.
 */
trait HasHint
{
    protected string|Closure|null $hint = null;

    protected string|Closure|null $hintIcon = null;

    protected string|Closure|null $hintColor = null;

    public function hint(string|Closure|null $hint): static
    {
        $this->hint = $hint;

        return $this;
    }

    public function hintIcon(string|Closure|null $icon): static
    {
        $this->hintIcon = $icon;

        return $this;
    }

    public function hintColor(string|Closure|null $color): static
    {
        $this->hintColor = $color;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->evaluate($this->hint);
    }

    public function getHintIcon(): ?string
    {
        return $this->evaluate($this->hintIcon);
    }

    public function getHintColor(): ?string
    {
        return $this->evaluate($this->hintColor);
    }
}
