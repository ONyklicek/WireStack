<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Enums;

/**
 * Canonical icon-position enum (`before`/`after`).
 *
 * Single owner of the "which side of the label the icon sits on" vocabulary
 * shared by every labelled surface with an icon (actions, buttons, columns), so
 * `->icon($i, 'after')` and `->icon($i, IconPosition::After)` are interchangeable.
 */
enum IconPosition: string
{
    case Before = 'before';
    case After = 'after';

    /**
     * Get all icon-position values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Resolve an icon-position token (or enum) to an IconPosition, falling back
     * to `$default` for anything unknown. Accepts an already-resolved enum unchanged.
     */
    public static function resolve(string|self $position, self $default = self::Before): self
    {
        if ($position instanceof self) {
            return $position;
        }

        return self::tryFrom($position) ?? $default;
    }
}
