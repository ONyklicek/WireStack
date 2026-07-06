<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Enums\IconPosition;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Foundation\Support\EvaluatesClosures;

/**
 * Provides an icon property for components.
 *
 * The using class must provide an `evaluate()` method (e.g. via the
 * {@see EvaluatesClosures} trait) so
 * closure-based icons can be resolved. Both Foundation\Components\Component
 * and the table DataComponent satisfy this, so fields and columns can share
 * the same icon API.
 */
trait HasIcon
{
    protected string|Closure|null $icon = null;

    protected ?string $iconPosition = 'before';

    public function icon(string|Icon|Closure|null $icon, string|IconPosition|null $position = null): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;

        if ($position !== null) {
            $this->iconPosition = $position instanceof IconPosition ? $position->value : $position;
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
