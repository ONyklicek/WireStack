<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\ShortcutLabelFormatter;

/**
 * The canonical shortcut-label formatter (selection gestures rollout, step 24).
 * The shortcut vocabulary is platform-neutral; only display picks a platform,
 * so `mod` never hard-codes Ctrl (ADR 0024 §1).
 */
it('formats modifiers per platform', function (string $shortcut, string $other, string $mac) {
    expect(ShortcutLabelFormatter::format($shortcut))->toBe($other)
        ->and(ShortcutLabelFormatter::format($shortcut, mac: true))->toBe($mac);
})->with([
    'mod' => ['mod+s', 'Ctrl+S', '⌘S'],
    'ctrl' => ['ctrl+d', 'Ctrl+D', '⌃D'],
    'control' => ['control+d', 'Ctrl+D', '⌃D'],
    'shift' => ['shift+ArrowUp', 'Shift+↑', '⇧↑'],
    'alt' => ['alt+f', 'Alt+F', '⌥F'],
    'option' => ['option+f', 'Alt+F', '⌥F'],
    'meta' => ['meta+k', '⌘+K', '⌘K'],
    'cmd' => ['cmd+k', '⌘+K', '⌘K'],
    'command' => ['command+k', '⌘+K', '⌘K'],
    'stacked modifiers' => ['mod+shift+ArrowDown', 'Ctrl+Shift+↓', '⌘⇧↓'],
]);

it('formats special keys with their glyphs', function (string $shortcut, string $label) {
    // Key glyphs are platform-independent; only modifiers and joining differ.
    expect(ShortcutLabelFormatter::format($shortcut))->toBe($label)
        ->and(ShortcutLabelFormatter::format($shortcut, mac: true))->toBe($label);
})->with([
    'enter' => ['Enter', '↵'],
    'return' => ['return', '↵'],
    'escape' => ['Escape', 'Esc'],
    'esc' => ['esc', 'Esc'],
    'delete' => ['Delete', 'Del'],
    'backspace' => ['Backspace', '⌫'],
    'space' => ['Space', 'Space'],
    'tab' => ['Tab', 'Tab'],
    'arrowup' => ['ArrowUp', '↑'],
    'up' => ['up', '↑'],
    'arrowdown' => ['ArrowDown', '↓'],
    'down' => ['down', '↓'],
    'arrowleft' => ['ArrowLeft', '←'],
    'left' => ['left', '←'],
    'arrowright' => ['ArrowRight', '→'],
    'right' => ['right', '→'],
    'home' => ['Home', 'Home'],
    'end' => ['End', 'End'],
    'pageup' => ['PageUp', 'PgUp'],
    'pagedown' => ['PageDown', 'PgDn'],
    'contextmenu' => ['ContextMenu', 'Menu'],
    'function key' => ['F10', 'F10'],
    'question mark' => ['?', '?'],
    'single letter uppercases' => ['a', 'A'],
]);

it('skips empty parts instead of rendering stray separators', function () {
    expect(ShortcutLabelFormatter::format('shift+'))->toBe('Shift')
        ->and(ShortcutLabelFormatter::format(' mod + s '))->toBe('Ctrl+S');
});
