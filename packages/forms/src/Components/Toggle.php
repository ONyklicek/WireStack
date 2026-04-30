<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;

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

    public function onColor(string $color): static
    {
        $this->onColor = $color;

        return $this;
    }

    public function offColor(string $color): static
    {
        $this->offColor = $color;

        return $this;
    }

    public function onIcon(?string $icon): static
    {
        $this->onIcon = $icon;

        return $this;
    }

    public function offIcon(?string $icon): static
    {
        $this->offIcon = $icon;

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

    public function isInline(): bool
    {
        return $this->inline;
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.toggle';
    }
}
