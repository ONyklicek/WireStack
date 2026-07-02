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
        public bool $slideOverOnMobile = false,
        public bool $stickyFooter = false,
        public bool $stickyHeader = false,
        public ?string $id = null,
        public ?string $closeAction = null,
    ) {}

    public function widthClass(): string
    {
        return HasModalProperties::getMaxWidthClass($this->width);
    }

    /**
     * The mobile (< sm) presentation variant: 'slide-over', 'full-screen', or
     * null for the default bottom-sheet-style dialog. Slide-over wins when both
     * mobile flags are set — it defines the shape, full-screen only the size.
     * Desktop rendering is identical for every variant.
     */
    public function mobileVariant(): ?string
    {
        return match (true) {
            $this->slideOverOnMobile => 'slide-over',
            $this->fullScreenOnMobile => 'full-screen',
            default => null,
        };
    }

    /**
     * Wrapper classes positioning the panel on mobile; ≥sm always falls back to
     * the inline-block centering layout.
     */
    public function containerClasses(): string
    {
        return match ($this->mobileVariant()) {
            // Edge-pinned right, full height, with the canonical slide-over's
            // 2.5rem breathing gap on the left.
            'slide-over' => 'flex min-h-screen items-stretch justify-end pl-10 text-center sm:block sm:p-0',
            'full-screen' => 'flex min-h-screen items-stretch justify-center text-center sm:block sm:p-0',
            default => 'flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0',
        };
    }

    /**
     * Panel classes for the mobile variant. The default keeps the original
     * bottom-sheet dialog; the mobile variants become an edge-to-edge flex
     * column so the body scrolls inside the panel instead of the page.
     */
    public function panelVariantClasses(): string
    {
        // The mobile variants switch the panel to a flex column (body scrolls
        // inside); `sm:inline-block` restores the inline centering layout that
        // desktop relies on (text-center wrapper + align-middle spacer).
        return match ($this->mobileVariant()) {
            'slide-over' => 'flex flex-col rounded-l-2xl rounded-r-none sm:inline-block sm:my-8 sm:rounded-2xl',
            'full-screen' => 'flex flex-col rounded-none sm:inline-block sm:my-8 sm:rounded-2xl',
            default => 'rounded-2xl sm:my-8',
        };
    }

    /**
     * Alpine transition classes per variant: mobile slides from the edge, ≥sm
     * keeps the fade + scale dialog transition.
     *
     * @return array{enterStart: string, enterEnd: string, leaveStart: string, leaveEnd: string}
     */
    public function transitionClasses(): array
    {
        return match ($this->mobileVariant()) {
            'slide-over' => [
                'enterStart' => 'translate-x-full sm:translate-x-0 sm:opacity-0 sm:scale-95',
                'enterEnd' => 'translate-x-0 sm:opacity-100 sm:scale-100',
                'leaveStart' => 'translate-x-0 sm:opacity-100 sm:scale-100',
                'leaveEnd' => 'translate-x-full sm:translate-x-0 sm:opacity-0 sm:scale-95',
            ],
            'full-screen' => [
                'enterStart' => 'translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95',
                'enterEnd' => 'translate-y-0 sm:opacity-100 sm:scale-100',
                'leaveStart' => 'translate-y-0 sm:opacity-100 sm:scale-100',
                'leaveEnd' => 'translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95',
            ],
            default => [
                'enterStart' => 'opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95',
                'enterEnd' => 'opacity-100 translate-y-0 sm:scale-100',
                'leaveStart' => 'opacity-100 translate-y-0 sm:scale-100',
                'leaveEnd' => 'opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95',
            ],
        };
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
