<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * Canonical font-weight class resolver.
 *
 * Single source for mapping a weight keyword to a Tailwind `font-*` utility, so
 * the same scale is shared across surfaces (table columns, infolist entries, …)
 * instead of each re-encoding the map. Class strings are kept literal for
 * Tailwind's JIT scanner (a safe allow-list); any unknown weight collapses to
 * `font-normal` rather than interpolating an unscannable `font-{$weight}`.
 */
trait HasFontWeight
{
    public static function getFontWeightClasses(string $weight): string
    {
        return match ($weight) {
            'thin' => 'font-thin',
            'extralight' => 'font-extralight',
            'light' => 'font-light',
            'medium' => 'font-medium',
            'semibold' => 'font-semibold',
            'bold' => 'font-bold',
            'extrabold' => 'font-extrabold',
            'black' => 'font-black',
            default => 'font-normal',
        };
    }
}
