<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Concerns;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Header icon + accent colour for a modal-style dialog.
 *
 * Owns the icon, its colour, and the dialog accent colour with their fluent
 * setters and normalised getters. Shared by the general Modal, SlideOver and
 * Wizard. ConfirmationDialog keeps its own variant (a danger-tinted default and
 * a null-preserving `icon()`), so it deliberately does not use this trait.
 */
trait HasModalIcon
{
    protected ?string $icon = null;

    protected ?string $iconColor = null;

    protected ?string $color = null;

    public function icon(string|Icon|null $icon, string|Color|null $color = null): static
    {
        $this->icon = $icon instanceof Icon ? $icon->value() : $icon;
        $this->iconColor = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function color(string|Color|null $color): static
    {
        $this->color = $color instanceof Color ? $color->value : $color;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getIconColor(): string
    {
        return $this->iconColor ?? Color::Gray->value;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }
}
