<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Enums;

use NyonCode\WireAdmin\Exceptions\NavigationShapeException;

/**
 * The shape the main navigation takes from `lg` up: a column beside the page,
 * or a bar under the header. Below `lg` it is the drawer either way — a bar is
 * a shape for a wide screen, not for a phone.
 */
enum NavigationShape: string
{
    case Sidebar = 'sidebar';
    case Top = 'top';

    /**
     * The shape a value names, refusing one that names none.
     *
     * Refused rather than defaulted: `'topbar'` in an env file quietly falling
     * back to the sidebar is a setting that looks applied and is not.
     */
    public static function resolve(self|string|null $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom($value ?? self::Sidebar->value)
            ?? throw NavigationShapeException::unknown((string) $value);
    }
}
