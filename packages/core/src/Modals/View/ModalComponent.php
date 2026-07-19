<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Modals\Support\ModalStyle;

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
 *
 * The consumer-facing tag; the framework itself renders the same shell via
 * `@include('wire-core::modals.modal', ['style' => new ModalStyle(...), ...])`
 * so the core renderer does not depend on this Blade component (Rule 5). All
 * presentation/layout logic lives in {@see ModalStyle}.
 */
class ModalComponent extends Component
{
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
        public bool $slideOverOnMobile = false,
        public bool $stickyFooter = false,
        public bool $stickyHeader = false,
        public ?string $id = null,
        public ?string $closeAction = null,
        public ?string $breakpoint = null,
        public ?int $zIndex = null,
    ) {}

    public function style(): ModalStyle
    {
        return new ModalStyle(
            width: $this->width,
            maxHeight: $this->maxHeight,
            fullScreenOnMobile: $this->fullScreenOnMobile,
            slideOverOnMobile: $this->slideOverOnMobile,
            iconColor: $this->iconColor,
            breakpoint: $this->breakpoint,
        );
    }

    public function render(): View
    {
        return view('wire-core::modals.modal', ['style' => $this->style()]);
    }
}
