<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Actions\Concerns;

use NyonCode\WireCore\Foundation\Support\ShortcutLabelFormatter;

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

    /**
     * Drop the shortcut for this copy of the action.
     *
     * A rendered button binds its shortcut as a *window* listener, so an action
     * rendered on more than one surface answers the same key once per surface —
     * and a surface that is merely present but not shown (the stacked mobile
     * cards on a desktop, say) would answer it invisibly, once per record.
     * A surface that must not own the key clones the action and calls this.
     */
    public function withoutKeyboardShortcut(): static
    {
        $this->keyboardShortcut = null;
        $this->keyboardShortcutLabel = null;

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
     * Delegates to the canonical {@see ShortcutLabelFormatter}, so an action
     * label and a shortcut legend always read the same.
     */
    protected function formatShortcutLabel(string $shortcut): string
    {
        return ShortcutLabelFormatter::format($shortcut);
    }
}
