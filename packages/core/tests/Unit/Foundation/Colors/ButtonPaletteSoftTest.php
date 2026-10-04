<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Colors\ButtonPalette;

/*
 * The soft (tinted pill) variant: a light ground, darker ink, a deeper ground on
 * hover — between ghost and solid.
 */

it('draws a hue as a tinted pill', function () {
    expect(ButtonPalette::soft('violet'))
        ->toBe('bg-violet-50 text-violet-700 hover:bg-violet-100 dark:bg-violet-900/30 dark:text-violet-300 dark:hover:bg-violet-900/50');
});

it('resolves a role through wire-core.colors', function () {
    expect(ButtonPalette::soft('warning'))->toContain('bg-amber-50');

    config()->set('wire-core.colors.warning', 'orange');

    expect(ButtonPalette::soft('warning'))->toContain('bg-orange-50');
});

it('has its own answer for black, white and anything unknown', function () {
    expect(ButtonPalette::soft('black'))->toContain('bg-gray-900 text-white')
        ->and(ButtonPalette::soft('white'))->toContain('bg-white text-gray-900')
        ->and(ButtonPalette::soft('not-a-colour'))->toBe('bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600');
});
