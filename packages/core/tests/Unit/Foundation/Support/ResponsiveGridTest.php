<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

it('maps a per-breakpoint columns map to literal grid-cols classes', function () {
    expect(ResponsiveGrid::cols(['default' => 1, 'md' => 2, 'lg' => 3]))
        ->toBe('grid-cols-1 md:grid-cols-2 lg:grid-cols-3');
});

it('treats default / empty / 0 breakpoint keys as the base (unprefixed) column', function () {
    expect(ResponsiveGrid::cols(['default' => 2]))->toBe('grid-cols-2')
        ->and(ResponsiveGrid::cols([0 => 2]))->toBe('grid-cols-2')
        ->and(ResponsiveGrid::cols(['' => 2]))->toBe('grid-cols-2');
});

it('supports every standard breakpoint prefix', function () {
    expect(ResponsiveGrid::cols(['sm' => 1, 'md' => 2, 'lg' => 3, 'xl' => 4, '2xl' => 6]))
        ->toBe('sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-6');
});

it('clamps column counts to the 1–12 grid scale', function () {
    expect(ResponsiveGrid::cols(['md' => 99]))->toBe('md:grid-cols-12')
        ->and(ResponsiveGrid::cols(['md' => 0]))->toBe('md:grid-cols-1')
        ->and(ResponsiveGrid::cols(['md' => -3]))->toBe('md:grid-cols-1');
});

it('maps an integer to a mobile-first reflow', function () {
    expect(ResponsiveGrid::cols(1))->toBe('grid-cols-1')
        ->and(ResponsiveGrid::cols(3))->toBe('grid-cols-1 md:grid-cols-3')
        ->and(ResponsiveGrid::cols(99))->toBe('grid-cols-1 md:grid-cols-12');
});

it('ignores unknown breakpoints and empty maps', function () {
    expect(ResponsiveGrid::cols(['bogus' => 2]))->toBe('')
        ->and(ResponsiveGrid::cols([]))->toBe('');
});

it('lists every emittable grid-cols class as a literal for the Tailwind scanner', function () {
    $classes = ResponsiveGrid::scannableClasses();

    // 6 breakpoints (base + sm/md/lg/xl/2xl) × 12 counts.
    expect($classes)->toHaveCount(72)
        ->toContain('grid-cols-1')
        ->toContain('lg:grid-cols-3')
        ->toContain('2xl:grid-cols-12');

    // Everything cols() can emit must appear in the literal allowlist.
    expect($classes)->toContain(ResponsiveGrid::cols(['xl' => 7]));
});
