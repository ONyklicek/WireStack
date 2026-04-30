<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

/**
 * Trait HasKeyboardShortcut
 *
 * Adds keyboard shortcut support to actions.
 *
 * Usage:
 *   Action::make('save')
 *       ->keyboardShortcut('mod+s')     // ctrl+s on Win/Linux, cmd+s on Mac
 *       ->keyboardShortcut('ctrl+d')    // explicit ctrl+d everywhere
 *       ->keyboardShortcut('Delete')    // single key
 *
 * Shortcut format:
 *   - 'mod+key' → resolves to ctrl on Win/Linux, cmd on Mac
 *   - 'ctrl+key', 'shift+key', 'alt+key', 'meta+key' → explicit modifiers
 *   - Modifiers can be combined: 'ctrl+shift+s'
 *   - Single keys: 'Delete', 'Enter', 'Escape', 'F1'-'F12'
 *
 * The Blade template renders an Alpine.js @keydown listener on the row or table.
 */
trait HasKeyboardShortcut
{
    protected ?string $keyboardShortcut = null;

    protected ?string $keyboardShortcutLabel = null;

    /**
     * Set a keyboard shortcut for this action.
     *
     * @param  string  $shortcut  e.g. 'mod+s', 'ctrl+d', 'Delete'
     * @param  string|null  $label  Display label override (e.g. '⌘S')
     */
    public function keyboardShortcut(string $shortcut, ?string $label = null): static
    {
        $this->keyboardShortcut = $shortcut;
        $this->keyboardShortcutLabel = $label;

        return $this;
    }

    public function getKeyboardShortcut(): ?string
    {
        return $this->keyboardShortcut;
    }

    /**
     * Get the display label for the shortcut.
     * Auto-generates from shortcut if not explicitly set.
     */
    public function getKeyboardShortcutLabel(): ?string
    {
        if ($this->keyboardShortcutLabel) {
            return $this->keyboardShortcutLabel;
        }

        if (! $this->keyboardShortcut) {
            return null;
        }

        return $this->formatShortcutLabel($this->keyboardShortcut);
    }

    /**
     * Get Alpine.js keydown expression for the shortcut.
     *
     * Converts 'mod+s' → '@keydown.ctrl.s.window.prevent' (with Mac detection in JS).
     */
    public function getAlpineKeydownExpression(): ?string
    {
        if (! $this->keyboardShortcut) {
            return null;
        }

        $parts = array_map('trim', explode('+', strtolower($this->keyboardShortcut)));
        $modifiers = [];
        $key = null;

        foreach ($parts as $part) {
            match ($part) {
                'mod' => $modifiers[] = 'ctrl', // Alpine handles mod differently, we use JS detection
                'ctrl', 'control' => $modifiers[] = 'ctrl',
                'shift' => $modifiers[] = 'shift',
                'alt', 'option' => $modifiers[] = 'alt',
                'meta', 'cmd', 'command' => $modifiers[] = 'meta',
                default => $key = $part,
            };
        }

        if (! $key) {
            return null;
        }

        // Map special keys to Alpine format
        $alpineKey = match ($key) {
            'delete' => 'delete',
            'enter', 'return' => 'enter',
            'escape', 'esc' => 'escape',
            'space' => 'space',
            'tab' => 'tab',
            'backspace' => 'backspace',
            'arrowup', 'up' => 'up',
            'arrowdown', 'down' => 'down',
            'arrowleft', 'left' => 'left',
            'arrowright', 'right' => 'right',
            default => $key,
        };

        $expr = implode('.', [...$modifiers, $alpineKey]);

        return $expr;
    }

    /**
     * Check if 'mod' is used (for Mac/Win detection in JS).
     */
    public function shortcutUsesMod(): bool
    {
        if (! $this->keyboardShortcut) {
            return false;
        }

        return str_contains(strtolower($this->keyboardShortcut), 'mod');
    }

    /**
     * Format shortcut for display (e.g. 'mod+s' → 'Ctrl+S' or '⌘S').
     */
    protected function formatShortcutLabel(string $shortcut): string
    {
        $parts = array_map('trim', explode('+', $shortcut));
        $formatted = [];

        foreach ($parts as $part) {
            $formatted[] = match (strtolower($part)) {
                'mod' => 'Ctrl',
                'ctrl', 'control' => 'Ctrl',
                'shift' => 'Shift',
                'alt', 'option' => 'Alt',
                'meta', 'cmd', 'command' => '⌘',
                'enter', 'return' => '↵',
                'escape', 'esc' => 'Esc',
                'delete' => 'Del',
                'backspace' => '⌫',
                'space' => 'Space',
                'tab' => 'Tab',
                default => strtoupper($part),
            };
        }

        return implode('+', $formatted);
    }
}
