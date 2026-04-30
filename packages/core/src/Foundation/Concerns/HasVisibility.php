<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Trait HasVisibility
 *
 * Shared visibility, disabled, and read-only state logic.
 * Used across form components, actions, columns, and filters.
 */
trait HasVisibility
{
    protected bool|Closure $isHidden = false;

    protected bool|Closure $isVisible = true;

    protected bool|Closure $isDisabled = false;

    public function visible(bool|Closure $condition = true): static
    {
        $this->isVisible = $condition;

        return $this;
    }

    public function hidden(bool|Closure $condition = true): static
    {
        $this->isHidden = $condition;

        return $this;
    }

    public function disabled(bool|Closure $condition = true): static
    {
        $this->isDisabled = $condition;

        return $this;
    }

    public function isHidden(): bool
    {
        return (bool) $this->evaluate($this->isHidden);
    }

    public function isVisible(): bool
    {
        return (bool) $this->evaluate($this->isVisible) && ! $this->isHidden();
    }

    public function isDisabled(): bool
    {
        return (bool) $this->evaluate($this->isDisabled);
    }
}
