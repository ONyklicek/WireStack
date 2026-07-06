<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Concerns\HasFontWeight;

/**
 * Canonical font-weight enum (`thin`…`black`).
 *
 * Single source of the weight vocabulary so a type-safe `->fontWeight(FontWeight::SemiBold)`
 * renders identically to the string `->fontWeight('semibold')`. The Tailwind
 * `font-*` class mapping stays in {@see HasFontWeight}
 * (literal, scannable `match` arms); this enum only owns the vocabulary + token
 * normalization.
 */
enum FontWeight: string
{
    case Thin = 'thin';
    case ExtraLight = 'extralight';
    case Light = 'light';
    case Normal = 'normal';
    case Medium = 'medium';
    case SemiBold = 'semibold';
    case Bold = 'bold';
    case ExtraBold = 'extrabold';
    case Black = 'black';

    /**
     * Get all font-weight values, from lightest to heaviest.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a weight token (or enum) to a FontWeight, falling back to `$default`
     * for anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $weight, self $default = self::Normal): self
    {
        if ($weight instanceof self) {
            return $weight;
        }

        return self::tryFrom($weight) ?? $default;
    }
}
