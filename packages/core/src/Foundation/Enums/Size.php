<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

use NyonCode\WireCore\Foundation\Concerns\HasSize;

/**
 * Canonical component-size enum (`xs`…`xl`).
 *
 * Single source of the size vocabulary shared by badge/button/icon surfaces so a
 * type-safe `->size(Size::Lg)` renders identically to the string `->size('lg')`.
 * The Tailwind class mapping itself stays in {@see HasSize}
 * (literal, scannable `match` arms); this enum only owns the vocabulary + token
 * normalization.
 */
enum Size: string
{
    case Xs = 'xs';
    case Sm = 'sm';
    case Md = 'md';
    case Lg = 'lg';
    case Xl = 'xl';

    /**
     * Get all size values, from smallest to largest.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve a size token (or enum) to a Size, falling back to `$default` for
     * anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $size, self $default = self::Md): self
    {
        if ($size instanceof self) {
            return $size;
        }

        return self::tryFrom($size) ?? $default;
    }
}
