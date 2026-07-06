<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\Alignment;

it('lists all alignment values', function () {
    expect(Alignment::values())->toBe(['left', 'center', 'right']);
});

it('resolves a token, an enum and unknown values', function () {
    expect(Alignment::resolve('center'))->toBe(Alignment::Center)
        ->and(Alignment::resolve(Alignment::Right))->toBe(Alignment::Right)
        ->and(Alignment::resolve('weird'))->toBe(Alignment::Left)
        ->and(Alignment::resolve('weird', Alignment::Right))->toBe(Alignment::Right);
});

it('maps every case to its text class', function () {
    expect(Alignment::Left->textClass())->toBe('text-left')
        ->and(Alignment::Center->textClass())->toBe('text-center')
        ->and(Alignment::Right->textClass())->toBe('text-right');
});

it('maps every case to its justify class', function () {
    expect(Alignment::Left->justifyClass())->toBe('justify-start')
        ->and(Alignment::Center->justifyClass())->toBe('justify-center')
        ->and(Alignment::Right->justifyClass())->toBe('justify-end');
});
