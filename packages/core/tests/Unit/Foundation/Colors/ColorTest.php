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

it('resolves aliases to standard colors', function () {
    expect(Color::resolve('blue'))->toBe(Color::Primary)
        ->and(Color::resolve('green'))->toBe(Color::Success)
        ->and(Color::resolve('red'))->toBe(Color::Danger)
        ->and(Color::resolve('yellow'))->toBe(Color::Warning)
        ->and(Color::resolve('cyan'))->toBe(Color::Info)
        ->and(Color::resolve('secondary'))->toBe(Color::Gray);
});

it('falls back to gray for unknown colors', function () {
    expect(Color::resolve('unknown'))->toBe(Color::Gray);
});
