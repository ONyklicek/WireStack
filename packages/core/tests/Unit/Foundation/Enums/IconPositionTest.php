<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\IconPosition;

it('lists all icon-position values', function () {
    expect(IconPosition::values())->toBe(['before', 'after']);
});

it('resolves a token, an enum and unknown values', function () {
    expect(IconPosition::resolve('after'))->toBe(IconPosition::After)
        ->and(IconPosition::resolve(IconPosition::Before))->toBe(IconPosition::Before)
        ->and(IconPosition::resolve('sideways'))->toBe(IconPosition::Before)
        ->and(IconPosition::resolve('sideways', IconPosition::After))->toBe(IconPosition::After);
});
