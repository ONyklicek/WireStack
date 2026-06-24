<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Toggle switch field with customizable colors, icons, and labels.
 */
class Toggle extends Field
{
    protected string|Closure|null $onLabel = null;

    protected string|Closure|null $offLabel = null;

    protected string $onColor = 'primary';

    protected string $offColor = 'gray';

    protected ?string $onIcon = null;

    protected ?string $offIcon = null;

    protected bool $inline = true;

    public function onLabel(string|Closure|null $label): static
    {
        $this->onLabel = $label;

        return $this;
    }

    public function offLabel(string|Closure|null $label): static
    {
        $this->offLabel = $label;

        return $this;
    }

    public function onColor(string|Color $color): static
    {
        $this->onColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function offColor(string|Color $color): static
    {
        $this->offColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function onIcon(string|Icon|null $icon): static
    {
        $this->onIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function offIcon(string|Icon|null $icon): static
    {
        $this->offIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    public function inline(bool $condition = true): static
    {
        $this->inline = $condition;

        return $this;
    }

    public function getOnLabel(): ?string
    {
        return $this->evaluate($this->onLabel);
    }

    public function getOffLabel(): ?string
    {
        return $this->evaluate($this->offLabel);
    }

    public function getOnColor(): string
    {
        return $this->onColor;
    }

    public function getOffColor(): string
    {
        return $this->offColor;
    }

    public function getOnIcon(): ?string
    {
        return $this->onIcon;
    }

    public function getOffIcon(): ?string
    {
        return $this->offIcon;
    }

    /**
     * Background utility class for the "on" track. Delegates to the canonical
     * solid-fill resolver in Foundation {@see HasColor::getSolidBgClass()} so the
     * toggle shares one palette with every other solid surface instead of
     * re-encoding hue rules locally.
     */
    public function getOnColorClasses(): string
    {
        return HasColor::getSolidBgClass($this->onColor);
    }

    /**
     * Background utility class for the "off" track. Delegates to the canonical
     * soft-fill resolver {@see HasColor::getSoftBgClass()}.
     */
    public function getOffColorClasses(): string
    {
        return HasColor::getSoftBgClass($this->offColor);
    }

    public function isInline(): bool
    {
        return $this->inline;
    }

    public function getStateType(): string
    {
        return 'bool';
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.toggle';
    }
}
