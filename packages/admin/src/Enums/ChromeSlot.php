<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Enums;

use NyonCode\WireAdmin\Exceptions\ChromeSlotException;

/**
 * Where a piece of the shell's chrome sits: in the top bar, or at the foot of
 * the menu column. Asked of the account menu (`wire-admin.layout.user`) and of
 * the button that collapses the menu into its rail
 * (`wire-admin.layout.rail_toggle`).
 */
enum ChromeSlot: string
{
    case Topbar = 'topbar';
    case Sidebar = 'sidebar';

    /**
     * The place a value names, refusing one that names none — for the same
     * reason as {@see NavigationShape::resolve()}: a typo quietly drawn in the
     * top bar is a setting that looks applied and is not.
     */
    public static function resolve(?string $value, string $setting): self
    {
        return self::tryFrom($value ?? self::Topbar->value)
            ?? throw ChromeSlotException::unknown((string) $value, $setting);
    }

    public function isSidebar(): bool
    {
        return $this === self::Sidebar;
    }
}
