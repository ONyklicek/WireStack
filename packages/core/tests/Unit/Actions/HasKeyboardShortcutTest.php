<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;

it('has no keyboard shortcut by default', function () {
    $action = Action::make('test');

    expect($action->getKeyboardShortcut())->toBeNull()
        ->and($action->getKeyboardShortcutLabel())->toBeNull()
        ->and($action->getAlpineKeydownExpression())->toBeNull();
});

it('can set keyboard shortcut', function () {
    $action = Action::make('save')->keyboardShortcut('mod+s');

    expect($action->getKeyboardShortcut())->toBe('mod+s');
});

it('can set custom shortcut label', function () {
    $action = Action::make('save')->keyboardShortcut('mod+s', '⌘S');

    expect($action->getKeyboardShortcutLabel())->toBe('⌘S');
});

it('auto-generates shortcut label from shortcut', function () {
    $action = Action::make('delete')->keyboardShortcut('Delete');

    expect($action->getKeyboardShortcutLabel())->not->toBeNull();
});

it('generates alpine keydown expression', function () {
    $action = Action::make('delete')->keyboardShortcut('ctrl+d');

    expect($action->getAlpineKeydownExpression())->not->toBeNull()
        ->and($action->getAlpineKeydownExpression())->toContain('ctrl');
});

it('detects mod shortcut', function () {
    $action = Action::make('save')->keyboardShortcut('mod+s');

    expect($action->shortcutUsesMod())->toBeTrue();
});

it('detects non-mod shortcut', function () {
    $action = Action::make('delete')->keyboardShortcut('Delete');

    expect($action->shortcutUsesMod())->toBeFalse();
});

// ─── Alpine keydown expressions ──────────────────────────────────────

it('maps each modifier to its Alpine name', function (string $shortcut, string $expected) {
    expect(Action::make('a')->keyboardShortcut($shortcut)->getAlpineKeydownExpression())->toBe($expected);
})->with([
    // mod resolves to ctrl here and is switched to meta in JS, where the
    // platform is actually known.
    'mod' => ['mod+s', 'ctrl.s'],
    'ctrl' => ['ctrl+s', 'ctrl.s'],
    'control' => ['control+s', 'ctrl.s'],
    'shift' => ['shift+s', 'shift.s'],
    'alt' => ['alt+s', 'alt.s'],
    'option' => ['option+s', 'alt.s'],
    'meta' => ['meta+s', 'meta.s'],
    'cmd' => ['cmd+s', 'meta.s'],
    'command' => ['command+s', 'meta.s'],
    'stacked' => ['ctrl+shift+s', 'ctrl.shift.s'],
]);

it('maps the named keys Alpine spells differently', function (string $shortcut, string $expected) {
    expect(Action::make('a')->keyboardShortcut($shortcut)->getAlpineKeydownExpression())->toBe($expected);
})->with([
    'delete' => ['Delete', 'delete'],
    'enter' => ['Enter', 'enter'],
    'return' => ['Return', 'enter'],
    'escape' => ['Escape', 'escape'],
    'esc' => ['Esc', 'escape'],
    'space' => ['Space', 'space'],
    'tab' => ['Tab', 'tab'],
    'backspace' => ['Backspace', 'backspace'],
    'arrowup' => ['ArrowUp', 'up'],
    'up' => ['up', 'up'],
    'arrowdown' => ['ArrowDown', 'down'],
    'down' => ['down', 'down'],
    'arrowleft' => ['ArrowLeft', 'left'],
    'left' => ['left', 'left'],
    'arrowright' => ['ArrowRight', 'right'],
    'right' => ['right', 'right'],
    'unmapped key passes through' => ['F5', 'f5'],
]);

it('has no expression for a shortcut that is only modifiers', function () {
    // Nothing to press: a modifier on its own is not a shortcut.
    expect(Action::make('a')->keyboardShortcut('ctrl+shift')->getAlpineKeydownExpression())->toBeNull();
});

it('reports no mod usage when no shortcut is set at all', function () {
    expect(Action::make('a')->shortcutUsesMod())->toBeFalse();
});

it('drops the shortcut for a surface that must not own the key', function () {
    // A rendered button binds its shortcut as a window listener, so an action
    // rendered on a second (possibly hidden) surface would answer the same key
    // twice over. That surface renders a copy with the key taken off.
    $action = Action::make('archive')->keyboardShortcut('Delete', '⌫');
    $copy = (clone $action)->withoutKeyboardShortcut();

    expect($copy->getKeyboardShortcut())->toBeNull()
        ->and($copy->getKeyboardShortcutLabel())->toBeNull()
        ->and($copy->getAlpineKeydownExpression())->toBeNull()
        // The original is untouched — it is the one the key still fires.
        ->and($action->getKeyboardShortcut())->toBe('Delete');
});
