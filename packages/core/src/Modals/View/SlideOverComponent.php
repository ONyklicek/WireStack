<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Modals\Support\SlideOverStyle;

/**
 * Blade component: <x-wire-modals::slide-over>
 *
 * A panel that slides in from the edge of the screen.
 *
 * Usage:
 *   <x-wire-modals::slide-over
 *       wire:model="showPanel"
 *       heading="User Details"
 *       position="right"
 *       width="md"
 *   >
 *       {{-- Panel body content --}}
 *   </x-wire-modals::slide-over>
 *
 * The consumer-facing tag; the framework itself renders the same shell via
 * `@include('wire-core::modals.slide-over', ['style' => new SlideOverStyle(...), ...])`
 * so the core renderer does not depend on this Blade component (Rule 5). All
 * presentation/layout logic lives in {@see SlideOverStyle}.
 */
class SlideOverComponent extends Component
{
    public function __construct(
        public ?string $heading = null,
        public ?string $description = null,
        public string $width = 'md',
        public string $position = 'right',
        public ?string $maxHeight = null,
        public bool $closeOnClickAway = true,
        public bool $closeOnEscape = true,
        public bool $stickyFooter = false,
        public bool $stickyHeader = false,
        public ?string $id = null,
        public ?string $closeAction = null,
        public bool $bottomSheetOnMobile = false,
        public ?string $breakpoint = null,
        public ?int $zIndex = null,
    ) {}

    public function style(): SlideOverStyle
    {
        return new SlideOverStyle(
            width: $this->width,
            position: $this->position,
            bottomSheetOnMobile: $this->bottomSheetOnMobile,
            breakpoint: $this->breakpoint,
        );
    }

    public function render(): View
    {
        return view('wire-core::modals.slide-over', ['style' => $this->style()]);
    }
}
