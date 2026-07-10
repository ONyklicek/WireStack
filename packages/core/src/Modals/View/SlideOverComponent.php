<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Support\MobileSheet;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

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
        public bool $bottomSheetOnMobile = false,
        public ?string $breakpoint = null,
        public ?int $zIndex = null,
    ) {}

    private function bp(): string
    {
        return MobileSheet::breakpoint($this->breakpoint);
    }

    public function widthClass(): string
    {
        // A responsive width (mobile full-width, capped above the breakpoint) lets
        // the bottom-sheet span edge-to-edge; the plain slide-over keeps its fixed
        // width at every breakpoint. Gate tracks the global mobile breakpoint.
        return HasModalProperties::getMaxWidthClass(
            $this->width,
            responsive: $this->bottomSheetOnMobile,
            breakpoint: $this->bp(),
        );
    }

    /**
     * Off-screen resting position the panel enters from / leaves to. The plain
     * slide-over always slides horizontally from its edge; the bottom-sheet
     * mobile mode slides up from the bottom (<sm) and only slides horizontally
     * from ≥sm where it becomes a real slide-over.
     */
    public function translateEnterStart(): string
    {
        return $this->offscreenTranslate();
    }

    public function translateLeaveEnd(): string
    {
        return $this->offscreenTranslate();
    }

    public function translateEnterEnd(): string
    {
        if (! $this->bottomSheetOnMobile) {
            return 'translate-x-0';
        }

        return match ($this->bp()) {
            'md' => 'translate-y-0 md:translate-x-0',
            'lg' => 'translate-y-0 lg:translate-x-0',
            default => 'translate-y-0 sm:translate-x-0',
        };
    }

    public function translateLeaveStart(): string
    {
        return $this->translateEnterEnd();
    }

    protected function offscreenTranslate(): string
    {
        // Full literal class strings only — Tailwind scans this file as text, so
        // an interpolated `{$bp}:{$x}` would never generate its CSS.
        if ($this->bottomSheetOnMobile) {
            $left = $this->position === 'left';

            return match ($this->bp()) {
                'md' => $left
                    ? 'translate-y-full md:translate-y-0 md:-translate-x-full'
                    : 'translate-y-full md:translate-y-0 md:translate-x-full',
                'lg' => $left
                    ? 'translate-y-full lg:translate-y-0 lg:-translate-x-full'
                    : 'translate-y-full lg:translate-y-0 lg:translate-x-full',
                default => $left
                    ? 'translate-y-full sm:translate-y-0 sm:-translate-x-full'
                    : 'translate-y-full sm:translate-y-0 sm:translate-x-full',
            };
        }

        return $this->position === 'left' ? '-translate-x-full' : 'translate-x-full';
    }

    public function positionClasses(): string
    {
        // Mobile: full-width tray pinned to the bottom edge. From the breakpoint
        // up: edge-pinned slide-over (full height, breathing gap on the inner
        // side). Literal strings per (breakpoint, position) so every utility is
        // scannable by Tailwind.
        if ($this->bottomSheetOnMobile) {
            $left = $this->position === 'left';

            return match ($this->bp()) {
                'md' => $left
                    ? 'inset-x-0 bottom-0 md:inset-x-auto md:top-0 md:bottom-0 md:left-0 md:pr-10'
                    : 'inset-x-0 bottom-0 md:inset-x-auto md:top-0 md:bottom-0 md:right-0 md:pl-10',
                'lg' => $left
                    ? 'inset-x-0 bottom-0 lg:inset-x-auto lg:top-0 lg:bottom-0 lg:left-0 lg:pr-10'
                    : 'inset-x-0 bottom-0 lg:inset-x-auto lg:top-0 lg:bottom-0 lg:right-0 lg:pl-10',
                default => $left
                    ? 'inset-x-0 bottom-0 sm:inset-x-auto sm:top-0 sm:bottom-0 sm:left-0 sm:pr-10'
                    : 'inset-x-0 bottom-0 sm:inset-x-auto sm:top-0 sm:bottom-0 sm:right-0 sm:pl-10',
            };
        }

        return $this->position === 'left'
            ? 'inset-y-0 left-0 pr-10'
            : 'inset-y-0 right-0 pl-10';
    }

    public function widthWrapperClasses(): string
    {
        if (! $this->bottomSheetOnMobile) {
            return 'w-screen';
        }

        return match ($this->bp()) {
            'md' => 'w-full md:w-screen',
            'lg' => 'w-full lg:w-screen',
            default => 'w-full sm:w-screen',
        };
    }

    public function panelClasses(): string
    {
        // Bottom-sheet mobile: capped height with rounded top corners so the body
        // scrolls inside the tray; above the breakpoint it returns to the
        // full-height square slide-over panel.
        if (! $this->bottomSheetOnMobile) {
            return 'h-full';
        }

        return match ($this->bp()) {
            'md' => 'max-h-[85vh] rounded-t-2xl md:h-full md:max-h-none md:rounded-none',
            'lg' => 'max-h-[85vh] rounded-t-2xl lg:h-full lg:max-h-none lg:rounded-none',
            default => 'max-h-[85vh] rounded-t-2xl sm:h-full sm:max-h-none sm:rounded-none',
        };
    }

    public function render(): View
    {
        return view('wire-core::modals.slide-over');
    }
}
