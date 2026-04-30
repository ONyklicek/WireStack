<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;

/**
 * Provides an icon property for components.
 */
trait HasIcon
{
    protected string|Closure|null $icon = null;

    protected ?string $iconPosition = 'before';

    public function icon(string|Closure|null $icon, ?string $position = null): static
    {
        $this->icon = $icon;

        if ($position !== null) {
            $this->iconPosition = $position;
        }

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->evaluate($this->icon);
    }

    public function getIconPosition(): string
    {
        return $this->iconPosition ?? 'before';
    }
}
