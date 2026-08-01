<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

/**
 * Canonical guard for record-supplied CSS color values.
 *
 * A color swatch is the one display surface that must interpolate a stored
 * value into a `style` attribute, where Blade's HTML escaping is not enough:
 * `e()` leaves `;` and `(` intact, so a stored value like
 * `red; background-image: url(https://evil.test/x)` would smuggle extra
 * declarations into the rule. Every surface that renders a swatch (table
 * ColorColumn, infolist ColorEntry) resolves the value through here first.
 *
 * The grammar is deliberately narrow — hex, the rgb/hsl function family, and
 * bare CSS keywords. Anything else resolves to null and the caller renders no
 * swatch rather than an unsafe one.
 */
final class CssColor
{
    /** `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`. */
    private const HEX = '/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i';

    /**
     * `rgb()` / `rgba()` / `hsl()` / `hsla()` with numeric, percentage, `deg`,
     * `none` and comma/space/slash separated arguments. No nested functions:
     * the argument list may not itself contain parentheses.
     */
    private const FUNCTIONAL = '/^(?:rgb|rgba|hsl|hsla)\(\s*[0-9a-z%.,\/\s+-]*\s*\)$/i';

    /** A bare CSS keyword — `red`, `rebeccapurple`, `transparent`, `currentColor`. */
    private const KEYWORD = '/^[a-z]{3,30}$/i';

    /**
     * Return the value if it is a CSS color safe to interpolate into a `style`
     * attribute, otherwise null.
     */
    public static function sanitize(mixed $value): ?string
    {
        if (! is_string($value) && ! (is_object($value) && method_exists($value, '__toString'))) {
            return null;
        }

        $color = trim((string) $value);

        if ($color === '') {
            return null;
        }

        foreach ([self::HEX, self::FUNCTIONAL, self::KEYWORD] as $pattern) {
            if (preg_match($pattern, $color) === 1) {
                return $color;
            }
        }

        return null;
    }
}
