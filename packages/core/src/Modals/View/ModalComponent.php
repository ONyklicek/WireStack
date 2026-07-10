<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\View;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Foundation\Support\MobileSheet;
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
        public ?string $breakpoint = null,
        public ?int $zIndex = null,
    ) {}

    private function bp(): string
    {
        return MobileSheet::breakpoint($this->breakpoint);
    }

    public function widthClass(): string
    {
        // The width cap gates at the same breakpoint as the sheet switch, so a
        // sheet is full-width below it and the dialog is capped above it.
        return HasModalProperties::getMaxWidthClass($this->width, breakpoint: $this->bp());
    }

    /**
     * The mobile (< sm) presentation variant: 'bottom-sheet', 'full-screen', or
     * null for the default centered dialog. `slideOverOnMobile` maps to a
     * bottom sheet that slides up from the bottom edge — the comfortable native
     * mobile pattern — rather than an awkward side panel on a phone. A desktop
     * slide-over is owned by {@see SlideOverComponent}; here the mobile flag only
     * governs the phone presentation. Bottom-sheet wins over full-screen when
     * both flags are set. Desktop rendering is identical for every variant.
     */
    public function mobileVariant(): ?string
    {
        return match (true) {
            $this->slideOverOnMobile => 'bottom-sheet',
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
        // The desktop-reset breakpoint tracks the global mobile breakpoint config
        // so a modal becomes a sheet up to the same width as dropdown panels.
        $reset = match ($this->bp()) {
            'md' => 'md:block md:p-0',
            'lg' => 'lg:block lg:p-0',
            default => 'sm:block sm:p-0',
        };

        return match ($this->mobileVariant()) {
            // Full-width tray flush to the bottom edge (slides up); no px/pb so it
            // spans edge-to-edge and sits against the bottom of the viewport.
            'bottom-sheet' => 'flex min-h-screen items-end justify-center text-center '.$reset,
            'full-screen' => 'flex min-h-screen items-stretch justify-center text-center '.$reset,
            default => 'flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center '.$reset,
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
        $bp = $this->bp();
        $inline = match ($bp) {
            'md' => 'md:inline-block md:my-8 md:rounded-2xl',
            'lg' => 'lg:inline-block lg:my-8 lg:rounded-2xl',
            default => 'sm:inline-block sm:my-8 sm:rounded-2xl',
        };
        $maxHReset = match ($bp) {
            'md' => 'md:max-h-none',
            'lg' => 'lg:max-h-none',
            default => 'sm:max-h-none',
        };
        $myOnly = match ($bp) {
            'md' => 'md:my-8',
            'lg' => 'lg:my-8',
            default => 'sm:my-8',
        };

        return match ($this->mobileVariant()) {
            // Capped-height sheet with rounded top corners; from the breakpoint up
            // it returns to the centered dialog with fully rounded corners.
            'bottom-sheet' => 'flex max-h-[85vh] flex-col rounded-t-2xl rounded-b-none '.$inline.' '.$maxHReset,
            'full-screen' => 'flex flex-col rounded-none '.$inline,
            default => 'rounded-2xl '.$myOnly,
        };
    }

    /**
     * Alpine transition classes per variant: mobile slides up from the bottom,
     * ≥sm keeps the fade + scale dialog transition.
     *
     * @return array{enterStart: string, enterEnd: string, leaveStart: string, leaveEnd: string}
     */
    public function transitionClasses(): array
    {
        $bp = $this->bp();

        if ($this->mobileVariant() !== null) {
            // Slide up from the bottom below the breakpoint; fade + scale above.
            return match ($bp) {
                'md' => [
                    'enterStart' => 'translate-y-full md:translate-y-0 md:opacity-0 md:scale-95',
                    'enterEnd' => 'translate-y-0 md:opacity-100 md:scale-100',
                    'leaveStart' => 'translate-y-0 md:opacity-100 md:scale-100',
                    'leaveEnd' => 'translate-y-full md:translate-y-0 md:opacity-0 md:scale-95',
                ],
                'lg' => [
                    'enterStart' => 'translate-y-full lg:translate-y-0 lg:opacity-0 lg:scale-95',
                    'enterEnd' => 'translate-y-0 lg:opacity-100 lg:scale-100',
                    'leaveStart' => 'translate-y-0 lg:opacity-100 lg:scale-100',
                    'leaveEnd' => 'translate-y-full lg:translate-y-0 lg:opacity-0 lg:scale-95',
                ],
                default => [
                    'enterStart' => 'translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95',
                    'enterEnd' => 'translate-y-0 sm:opacity-100 sm:scale-100',
                    'leaveStart' => 'translate-y-0 sm:opacity-100 sm:scale-100',
                    'leaveEnd' => 'translate-y-full sm:translate-y-0 sm:opacity-0 sm:scale-95',
                ],
            };
        }

        // Plain centered dialog: small slide-up + scale.
        return match ($bp) {
            'md' => [
                'enterStart' => 'opacity-0 translate-y-4 md:translate-y-0 md:scale-95',
                'enterEnd' => 'opacity-100 translate-y-0 md:scale-100',
                'leaveStart' => 'opacity-100 translate-y-0 md:scale-100',
                'leaveEnd' => 'opacity-0 translate-y-4 md:translate-y-0 md:scale-95',
            ],
            'lg' => [
                'enterStart' => 'opacity-0 translate-y-4 lg:translate-y-0 lg:scale-95',
                'enterEnd' => 'opacity-100 translate-y-0 lg:scale-100',
                'leaveStart' => 'opacity-100 translate-y-0 lg:scale-100',
                'leaveEnd' => 'opacity-0 translate-y-4 lg:translate-y-0 lg:scale-95',
            ],
            default => [
                'enterStart' => 'opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95',
                'enterEnd' => 'opacity-100 translate-y-0 sm:scale-100',
                'leaveStart' => 'opacity-100 translate-y-0 sm:scale-100',
                'leaveEnd' => 'opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95',
            ],
        };
    }

    /**
     * Body classes: below the breakpoint the mobile variants scroll the body
     * inside the full-height panel; from the breakpoint up they return to the
     * page-scroll layout (unless maxHeight opts the body into its own scrollbar).
     */
    public function bodyVariantClasses(): string
    {
        if ($this->mobileVariant() === null) {
            return '';
        }

        $flexNone = match ($this->bp()) {
            'md' => 'md:flex-none',
            'lg' => 'lg:flex-none',
            default => 'sm:flex-none',
        };
        $overflowVisible = match ($this->bp()) {
            'md' => 'md:overflow-visible',
            'lg' => 'lg:overflow-visible',
            default => 'sm:overflow-visible',
        };

        return $this->maxHeight
            ? 'flex-1 overflow-y-auto overscroll-contain '.$flexNone
            : 'flex-1 overflow-y-auto overscroll-contain '.$flexNone.' '.$overflowVisible;
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
