<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;

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

    public function hintIcon(string|Icon|Closure|null $icon): static
    {
        $this->hintIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function hintColor(string|Color|Closure|null $color): static
    {
        $this->hintColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getHint(): ?string
    {
        return $this->evaluate($this->hint);
    }

    public function getHintIcon(): ?string
    {
        $value = $this->evaluate($this->hintIcon);

        return $value instanceof Icon ? $value->value() : $value;
    }

    public function getHintColor(): ?string
    {
        $value = $this->evaluate($this->hintColor);

        return $value instanceof Color ? $value->value : $value;
    }
}
