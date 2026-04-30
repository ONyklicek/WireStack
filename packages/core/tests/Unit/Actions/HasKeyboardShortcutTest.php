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
