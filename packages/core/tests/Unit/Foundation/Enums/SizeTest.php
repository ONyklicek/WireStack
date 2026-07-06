<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\Size;

it('lists all size values from smallest to largest', function () {
    expect(Size::values())->toBe(['xs', 'sm', 'md', 'lg', 'xl']);
});

it('resolves a raw token to the matching case', function () {
    expect(Size::resolve('xs'))->toBe(Size::Xs)
        ->and(Size::resolve('sm'))->toBe(Size::Sm)
        ->and(Size::resolve('md'))->toBe(Size::Md)
        ->and(Size::resolve('lg'))->toBe(Size::Lg)
        ->and(Size::resolve('xl'))->toBe(Size::Xl);
});

it('passes an already-resolved enum through resolve unchanged', function () {
    expect(Size::resolve(Size::Xl))->toBe(Size::Xl);
});

it('falls back to the default for unknown tokens', function () {
    expect(Size::resolve('huge'))->toBe(Size::Md)
        ->and(Size::resolve('huge', Size::Lg))->toBe(Size::Lg);
});
