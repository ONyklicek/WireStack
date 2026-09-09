<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals;

use NyonCode\WireCore\Modals\Concerns\HasFooterActions;
use NyonCode\WireCore\Modals\Concerns\HasModalIcon;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;
use NyonCode\WireCore\Modals\Contracts\ModalContract;

/**
 * General-purpose modal configuration.
 *
 * Used as a building block for action modals, form modals,
 * and standalone modal dialogs. Not a Blade component itself —
 * provides configuration that Blade view components consume.
 *
 * Usage:
 *   Modal::make()
 *       ->heading('Edit Record')
 *       ->description('Update the record details below.')
 *       ->width('lg')
 *       ->closeOnClickAway(false);
 *
 * @phpstan-consistent-constructor
 */
class Modal implements ModalContract
{
    use HasFooterActions;
    use HasModalIcon;
    use HasModalProperties;

    protected bool $fullScreenOnMobile = false;

    protected ?string $mobileWidth = null;

    public function __construct()
    {
        try {
            $this->width = config('wire-core.modals.default_width', 'md') ?? 'md';
        } catch (\Throwable) {
            // Standalone usage without Laravel container
        }
    }

    public static function make(): static
    {
        return new static;
    }

    public function fullScreenOnMobile(bool $fullScreen = true): static
    {
        $this->fullScreenOnMobile = $fullScreen;

        return $this;
    }

    public function mobileWidth(string $width): static
    {
        $this->mobileWidth = $width;

        return $this;
    }

    public function isFullScreenOnMobile(): bool
    {
        return $this->fullScreenOnMobile;
    }

    public function getMobileWidth(): ?string
    {
        return $this->mobileWidth;
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
            'fullScreenOnMobile' => $this->isFullScreenOnMobile(),
            'mobileWidth' => $this->getMobileWidth(),
            'submitLabel' => $this->getSubmitLabel(),
            'cancelLabel' => $this->getCancelLabel(),
            'stickyFooter' => $this->hasStickyFooter(),
            'stickyHeader' => $this->hasStickyHeader(),
        ];
    }
}
