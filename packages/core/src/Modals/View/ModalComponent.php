<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

/**
 * Blade component: <x-wire-modals::modal>
 *
 * A general-purpose modal dialog with Alpine.js transitions.
 *
 * Usage:
 *   <x-wire-modals::modal
 *       wire:model="showModal"
 *       heading="Edit Record"
 *       description="Update the details."
 *       width="lg"
 *   >
 *       {{-- Modal body content --}}
 *   </x-wire-modals::modal>
 */
class ModalComponent extends Component
{
    use HasColor;

    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public string $width = 'md',
        public ?string $icon = null,
        public string $iconColor = 'gray',
        public ?string $maxHeight = null,
        public bool $closeOnClickAway = true,
        public bool $closeOnEscape = true,
        public bool $fullScreenOnMobile = false,
        public bool $stickyFooter = false,
        public bool $stickyHeader = false,
        public ?string $id = null,
        public ?string $closeAction = null,
    ) {}

    public function widthClass(): string
    {
        return HasModalProperties::getMaxWidthClass($this->width);
    }

    public function iconBgClass(): string
    {
        return self::getModalIconBgClass($this->iconColor);
    }

    public function iconColorClass(): string
    {
        return self::getModalIconTextClass($this->iconColor);
    }

    /**
     * Modal chrome has no accent color of its own; HasColor is consumed
     * only for its static icon-chip helpers.
     */
    protected function getColor(): ?string
    {
        return null;
    }

    public function render(): View
    {
        return view('wire-core::modals.modal');
    }
}
