<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Components\Component;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Provides an icon property for components.
 *
 * @phpstan-require-extends Component
 */
trait HasIcon
{
    protected string|Closure|null $icon = null;

    protected ?string $iconPosition = 'before';

    public function icon(string|Icon|Closure|null $icon, ?string $position = null): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        if ($position !== null) {
            $this->iconPosition = $position;
        }

        return $this;
    }

    public function getIcon(): ?string
    {
        $value = $this->evaluate($this->icon);

        return $value instanceof Icon ? $value->value() : $value;
    }

    public function getIconPosition(): string
    {
        return $this->iconPosition ?? 'before';
    }
}
