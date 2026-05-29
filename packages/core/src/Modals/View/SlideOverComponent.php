<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

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
    ) {}

    public function widthClass(): string
    {
        return match ($this->width) {
            'sm' => 'max-w-sm',
            'lg' => 'max-w-lg',
            'xl' => 'max-w-xl',
            '2xl' => 'max-w-2xl',
            default => 'max-w-md',
        };
    }

    public function translateEnterStart(): string
    {
        return $this->position === 'left' ? '-translate-x-full' : 'translate-x-full';
    }

    public function translateLeaveEnd(): string
    {
        return $this->position === 'left' ? '-translate-x-full' : 'translate-x-full';
    }

    public function positionClasses(): string
    {
        return $this->position === 'left' ? 'left-0' : 'right-0';
    }

    public function render(): View
    {
        return view('wire-core::modals.slide-over');
    }
}
