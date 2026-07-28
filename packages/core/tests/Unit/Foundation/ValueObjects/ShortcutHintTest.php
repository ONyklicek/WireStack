<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\ValueObjects\ShortcutHint;

/**
 * The legend-row value object (selection gestures rollout, step 24): pure data
 * — equivalent keys + a localized description — shared by every surface that
 * renders a shortcut legend.
 */
it('normalizes a single key to a list', function () {
    $hint = ShortcutHint::make('mod+a', 'Select the whole page');

    expect($hint->keys)->toBe(['mod+a'])
        ->and($hint->description)->toBe('Select the whole page');
});

it('keeps multiple equivalent keys in order', function () {
    $hint = ShortcutHint::make(['Delete', 'Backspace'], 'Remove');

    expect($hint->keys)->toBe(['Delete', 'Backspace']);
});

it('formats its keys per platform through the canonical formatter', function () {
    $hint = ShortcutHint::make(['mod+shift+ArrowUp', 'shift+Home'], 'Extend');

    expect($hint->labels())->toBe(['Ctrl+Shift+↑', 'Shift+Home'])
        ->and($hint->labels(mac: true))->toBe(['⌘⇧↑', '⇧Home']);
});

it('has a stable signature regardless of key order and casing', function () {
    $a = ShortcutHint::make(['Delete', 'Backspace'], 'Remove');
    $b = ShortcutHint::make(['backspace', 'DELETE'], 'Anything else');
    $c = ShortcutHint::make(['Delete'], 'Remove');

    expect($a->signature())->toBe($b->signature())
        ->and($a->signature())->not->toBe($c->signature());
});
