<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Support;

/**
 * Safe translation helper that falls back gracefully when
 * the Laravel translator is not available (e.g. in pure unit tests).
 *
 * Usage:
 *   Trans::get('wire-core::actions.confirm_submit')
 *   // Returns translated string or the key's last segment as fallback
 */
final class Trans
{
    /**
     * Translate the given key, falling back to the last segment if translator is unavailable.
     *
     * @param  array<string, string>  $replace
     */
    public static function get(string $key, array $replace = []): string
    {
        if (function_exists('app') && app()->bound('translator')) {
            return __($key, $replace);
        }

        // Fallback: extract last segment after '.' and '::'
        $fallback = $key;
        if (str_contains($fallback, '::')) {
            $fallback = substr($fallback, strpos($fallback, '::') + 2);
        }
        if (str_contains($fallback, '.')) {
            $fallback = substr($fallback, strrpos($fallback, '.') + 1);
        }

        // Apply simple :placeholder replacements
        foreach ($replace as $placeholder => $value) {
            $fallback = str_replace(':'.$placeholder, $value, $fallback);
        }

        return $fallback;
    }
}
