<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\Color;

it('has all standard colors', function () {
    $colors = Color::values();

    expect($colors)->toContain('primary')
        ->toContain('success')
        ->toContain('danger')
        ->toContain('warning')
        ->toContain('info')
        ->toContain('gray');
});

it('resolves true semantic aliases to their role', function () {
    expect(Color::resolve('emerald'))->toBe(Color::Success)
        ->and(Color::resolve('amber'))->toBe(Color::Warning)
        ->and(Color::resolve('secondary'))->toBe(Color::Gray);
});

it('resolves literal hues to their own member, not the semantic role', function () {
    expect(Color::resolve('blue'))->toBe(Color::Blue)
        ->and(Color::resolve('green'))->toBe(Color::Green)
        ->and(Color::resolve('red'))->toBe(Color::Red)
        ->and(Color::resolve('yellow'))->toBe(Color::Yellow)
        ->and(Color::resolve('cyan'))->toBe(Color::Cyan);
});

it('resolves the achromatic endpoints', function () {
    expect(Color::resolve('white'))->toBe(Color::White)
        ->and(Color::resolve('black'))->toBe(Color::Black);
});

it('falls back to gray for unknown colors', function () {
    expect(Color::resolve('unknown'))->toBe(Color::Gray);
});

it('tells a real color from a misspelt one', function () {
    // resolve() greys out a typo silently; tryResolve() is how tooling notices.
    expect(Color::tryResolve('rose'))->toBe(Color::Rose)
        ->and(Color::tryResolve('bleu'))->toBeNull()
        ->and(Color::tryResolve(''))->toBeNull();
});

it('distinguishes a deliberate gray from an unknown color', function () {
    expect(Color::tryResolve('gray'))->toBe(Color::Gray)
        ->and(Color::tryResolve('secondary'))->toBe(Color::Gray)
        ->and(Color::tryResolve('graey'))->toBeNull();
});

it('resolves every enum value back to itself', function () {
    foreach (Color::values() as $value) {
        expect(Color::tryResolve($value))->toBe(Color::from($value));
    }
});
