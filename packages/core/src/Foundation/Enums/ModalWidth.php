<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Modals\Concerns\HasModalProperties;

/**
 * Canonical modal/dialog/slide-over width enum (`sm`…`7xl`, `full`).
 *
 * Owns the Tailwind `max-w-*` width vocabulary shared by the modal, confirmation
 * dialog and slide-over surfaces, so `->modalWidth('2xl')` and
 * `->modalWidth(ModalWidth::TwoXl)` are interchangeable. The token → `max-w-*`
 * class mapping stays in {@see HasModalProperties::getMaxWidthClass()}
 * (literal, scannable, breakpoint-aware); this enum only owns the vocabulary +
 * token normalization.
 */
enum ModalWidth: string
{
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';
    case TwoXl = '2xl';
    case ThreeXl = '3xl';
    case FourXl = '4xl';
    case FiveXl = '5xl';
    case SixXl = '6xl';
    case SevenXl = '7xl';
    case Full = 'full';

    /**
     * Get all width values, from narrowest to widest.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a width token (or enum) to a ModalWidth, falling back to `$default`
     * for anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $width, self $default = self::Md): self
    {
        if ($width instanceof self) {
            return $width;
        }

        return self::tryFrom($width) ?? $default;
    }
}
