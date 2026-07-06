<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Enums\Placement;

it('lists all placement values', function () {
    expect(Placement::values())->toBe(['bottom-start', 'bottom-end', 'top-start', 'top-end']);
});

it('resolves a token, an enum and unknown values', function () {
    expect(Placement::resolve('top-end'))->toBe(Placement::TopEnd)
        ->and(Placement::resolve(Placement::TopStart))->toBe(Placement::TopStart)
        ->and(Placement::resolve('nope'))->toBe(Placement::BottomEnd)
        ->and(Placement::resolve('nope', Placement::TopStart))->toBe(Placement::TopStart);
});

it('maps every case to its panel position classes', function () {
    expect(Placement::BottomStart->panelClasses())->toBe('left-0 origin-top-left')
        ->and(Placement::BottomEnd->panelClasses())->toBe('right-0 origin-top-right')
        ->and(Placement::TopStart->panelClasses())->toBe('left-0 bottom-full origin-bottom-left')
        ->and(Placement::TopEnd->panelClasses())->toBe('right-0 bottom-full origin-bottom-right');
});

it('maps every case to its transform-origin class', function () {
    expect(Placement::BottomStart->originClass())->toBe('origin-top-left')
        ->and(Placement::BottomEnd->originClass())->toBe('origin-top-right')
        ->and(Placement::TopStart->originClass())->toBe('origin-bottom-left')
        ->and(Placement::TopEnd->originClass())->toBe('origin-bottom-right');
});
