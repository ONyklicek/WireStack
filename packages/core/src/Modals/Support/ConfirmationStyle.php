<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Modals\Support;

use NyonCode\WireCore\Foundation\Concerns\HasColor;
use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

/**
 * Presentation resolver for the confirmation dialog shell.
 *
 * Extracted verbatim from `ConfirmationComponent` (Rule 5 framework-wide,
 * Phase 0) so the confirmation shell can be rendered without the Blade
 * component and unit-tested in isolation. Per AI_CODING_STANDARD, presentation
 * logic belongs in a Support value object, not in the component.
 */
final class ConfirmationStyle
{
    public function __construct(
        private readonly string $width = 'md',
        private readonly string $iconColor = 'warning',
        private readonly ?string $color = null,
    ) {}

    public function widthClass(): string
    {
        return HasModalProperties::getMaxWidthClass($this->width);
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
