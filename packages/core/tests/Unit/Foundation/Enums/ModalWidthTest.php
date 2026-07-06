<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\ModalWidth;

it('lists all width values from narrowest to widest', function () {
    expect(ModalWidth::values())->toBe(['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl', '6xl', '7xl', 'full']);
});

it('resolves a token, an enum and unknown values', function () {
    expect(ModalWidth::resolve('2xl'))->toBe(ModalWidth::TwoXl)
        ->and(ModalWidth::resolve(ModalWidth::Full))->toBe(ModalWidth::Full)
        ->and(ModalWidth::resolve('gigantic'))->toBe(ModalWidth::Md)
        ->and(ModalWidth::resolve('gigantic', ModalWidth::Lg))->toBe(ModalWidth::Lg);
});
