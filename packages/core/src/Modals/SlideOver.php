<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals;

use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireCore\Modals\Concerns\HasFooterActions;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;
use NyonCode\WireCore\Modals\Contracts\ModalContract;

/**
 * Slide-over panel configuration.
 *
 * A panel that slides in from the right side of the screen.
 * Can be configured to only appear as a slide-over on mobile
 * while remaining a standard modal on desktop.
 *
 * Usage:
 *   SlideOver::make()
 *       ->heading('User Details')
 *       ->description('View and edit user information.')
 *       ->width('lg')
 *       ->position('right');
 *
 * @phpstan-consistent-constructor
 */
class SlideOver implements ModalContract
{
    use HasFooterActions;
    use HasModalProperties;

    protected ?string $icon = null;

    protected ?string $iconColor = null;

    protected ?string $color = null;

    protected string $position = 'right';

    protected bool $mobileOnly = false;

    public function __construct()
    {
        try {
            $this->width = config('wire-core.modals.slide_over_width', 'md') ?? 'md';
        } catch (\Throwable) {
            // Standalone usage without Laravel container
        }
    }

    public static function make(): static
    {
        return new static;
    }

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

    /**
     * Set slide-over position ('left' or 'right').
     */
    public function position(string $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * Only show as slide-over on mobile; regular modal on desktop.
     */
    public function mobileOnly(bool $mobileOnly = true): static
    {
        $this->mobileOnly = $mobileOnly;

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

    public function getPosition(): string
    {
        return $this->position;
    }

    public function isMobileOnly(): bool
    {
        return $this->mobileOnly;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'heading' => $this->getHeading(),
            'description' => $this->getDescription(),
            'icon' => $this->getIcon(),
            'iconColor' => $this->getIconColor(),
            'color' => $this->getColor(),
            'width' => $this->getWidth(),
            'maxHeight' => $this->getMaxHeight(),
            'closeOnClickAway' => $this->shouldCloseOnClickAway(),
            'closeOnEscape' => $this->shouldCloseOnEscape(),
            'submitLabel' => $this->getSubmitLabel(),
            'cancelLabel' => $this->getCancelLabel(),
            'position' => $this->getPosition(),
            'mobileOnly' => $this->isMobileOnly(),
            'stickyFooter' => $this->hasStickyFooter(),
            'stickyHeader' => $this->hasStickyHeader(),
        ];
    }
}
