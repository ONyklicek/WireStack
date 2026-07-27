<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Actions\Concerns\HasKeyboardShortcut;

/**
 * Canonical shortcut-label formatter — the single owner of how a shortcut
 * string reads on screen (`mod+s` → `Ctrl+S`, or `⌘S` on macOS).
 *
 * The shortcut vocabulary itself stays platform-neutral (`mod`, `ctrl`,
 * `shift`, key names); the platform is decided only at display time via the
 * `$mac` flag, so PHP never bakes one platform's chrome into a canonical
 * string. Consumers: {@see HasKeyboardShortcut}
 * label generation and every shortcut legend (table `?` help, palette, wizard).
 */
final class ShortcutLabelFormatter
{
    /**
     * Format a canonical shortcut string (`mod+shift+ArrowUp`, `Delete`, `?`)
     * for display. macOS joins glyphs without a separator (`⌘⇧↑`); other
     * platforms join words with `+` (`Ctrl+Shift+↑`).
     */
    public static function format(string $shortcut, bool $mac = false): string
    {
        $formatted = [];

        foreach (explode('+', $shortcut) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $formatted[] = self::part($part, $mac);
        }

        return implode($mac ? '' : '+', $formatted);
    }

    private static function part(string $part, bool $mac): string
    {
        return match (strtolower($part)) {
            'mod' => $mac ? '⌘' : 'Ctrl',
            'ctrl', 'control' => $mac ? '⌃' : 'Ctrl',
            'shift' => $mac ? '⇧' : 'Shift',
            'alt', 'option' => $mac ? '⌥' : 'Alt',
            'meta', 'cmd', 'command' => '⌘',
            'enter', 'return' => '↵',
            'escape', 'esc' => 'Esc',
            'delete' => 'Del',
            'backspace' => '⌫',
            'space' => 'Space',
            'tab' => 'Tab',
            'arrowup', 'up' => '↑',
            'arrowdown', 'down' => '↓',
            'arrowleft', 'left' => '←',
            'arrowright', 'right' => '→',
            'home' => 'Home',
            'end' => 'End',
            'pageup' => 'PgUp',
            'pagedown' => 'PgDn',
            'contextmenu' => 'Menu',
            default => mb_strlen($part) === 1
                ? mb_strtoupper($part)
                : ucfirst(strtolower($part)),
        };
    }
}
