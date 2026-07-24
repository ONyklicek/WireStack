<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Support;

use NyonCode\WireCore\Foundation\Concerns\HasColor;

/**
 * Presentation resolver for the confirmation dialog shell.
 *
 * Extracted verbatim from `ConfirmationComponent` (Rule 5 framework-wide,
 * Phase 0) so the confirmation shell can be rendered without the Blade
 * component and unit-tested in isolation. Per AI_CODING_STANDARD, presentation
 * logic belongs in a Support value object, not in the component.
 *
 * The mobile-sheet variant (bottom-sheet / full-screen below the breakpoint) is
 * owned canonically by {@see ModalStyle}; this resolver composes one and
 * delegates every variant class to it rather than re-encoding a parallel map, so
 * a confirmation honours `slideOverOnMobile()` / `fullScreenOnMobile()` exactly
 * like a form modal does.
 */
final class ConfirmationStyle
{
    private readonly ModalStyle $layout;

    public function __construct(
        private readonly string $width = 'md',
        private readonly string $iconColor = 'warning',
        private readonly ?string $color = null,
        bool $fullScreenOnMobile = false,
        bool $slideOverOnMobile = false,
        ?string $breakpoint = null,
    ) {
        $this->layout = new ModalStyle(
            width: $width,
            fullScreenOnMobile: $fullScreenOnMobile,
            slideOverOnMobile: $slideOverOnMobile,
            iconColor: $iconColor,
            breakpoint: $breakpoint,
        );
    }

    /**
     * The mobile (< breakpoint) presentation variant: 'bottom-sheet',
     * 'full-screen', or null for the default centered dialog.
     */
    public function mobileVariant(): ?string
    {
        return $this->layout->mobileVariant();
    }

    public function widthClass(): string
    {
        return $this->layout->widthClass();
    }

    /**
     * Panel width behaviour: a mobile sheet is edge-to-edge below the breakpoint
     * (`w-full`); the centered dialog keeps its historical shrink-to-content
     * width (`sm:w-full`). Both cap at the same gated max-width above it.
     */
    public function panelWidthClass(): string
    {
        $stretch = $this->mobileVariant() !== null ? 'w-full' : 'sm:w-full';

        return $stretch.' '.$this->layout->widthClass();
    }

    /** Wrapper classes positioning the dialog/sheet on mobile. */
    public function containerClasses(): string
    {
        return $this->layout->containerClasses();
    }

    /** Panel classes for the mobile variant (rounding / flex column / sheet). */
    public function panelVariantClasses(): string
    {
        return $this->layout->panelVariantClasses();
    }

    /**
     * Alpine transition classes per variant: a mobile sheet slides up from the
     * bottom; the centered dialog keeps the fade + scale transition.
     *
     * @return array{enterStart: string, enterEnd: string, leaveStart: string, leaveEnd: string}
     */
    public function transitionClasses(): array
    {
        return $this->layout->transitionClasses();
    }

    public function iconBgClass(): string
    {
        return HasColor::getModalIconBgClass($this->iconColor);
    }

    public function iconColorClass(): string
    {
        return HasColor::getModalIconTextClass($this->iconColor);
    }

    public function submitButtonClasses(): string
    {
        $base = 'inline-flex w-full justify-center rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm sm:w-auto';

        // Delegate the hue map to the canonical owner so this footer stays in
        // sync with the table action modal footers instead of re-encoding it.
        return "{$base} ".HasColor::getModalSubmitButtonClasses($this->color ?? 'primary');
    }
}
