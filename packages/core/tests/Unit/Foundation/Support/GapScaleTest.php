<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Flex;
use NyonCode\WireCore\Foundation\Support\GapScale;

// ─── The scale ─────────────────────────────────────────────────────

test('every step on the scale has a literal utility', function () {
    foreach (range(0, 12) as $step) {
        expect(GapScale::classFor($step))->toBe("gap-{$step}");
    }
});

test('a step past either end is clamped rather than invented', function () {
    expect(GapScale::classFor(-4))->toBe('gap-0')
        ->and(GapScale::classFor(99))->toBe('gap-12');
});

// ─── The two vocabularies ──────────────────────────────────────────

test('the design-system names land on the numeric scale', function () {
    expect(GapScale::classFor('none'))->toBe('gap-0')
        ->and(GapScale::classFor('xs'))->toBe('gap-1')
        ->and(GapScale::classFor('sm'))->toBe('gap-2')
        ->and(GapScale::classFor('md'))->toBe('gap-4')
        ->and(GapScale::classFor('lg'))->toBe('gap-6')
        ->and(GapScale::classFor('xl'))->toBe('gap-8');
});

test('a name is read whatever its casing or padding', function () {
    expect(GapScale::classFor('  SM '))->toBe('gap-2');
});

test('a numeric string is a step, not a name', function () {
    expect(GapScale::classFor('7'))->toBe('gap-7');
});

// ─── The fallback ──────────────────────────────────────────────────

// The point of a fallback here is that nothing unrecognised may reach the DOM as
// a class Tailwind never generated — that is the defect this class was extracted
// to close, and it renders as no gap at all rather than as an error.
test('an unrecognised token falls back, and the caller chooses to what', function () {
    expect(GapScale::classFor('huge'))->toBe('gap-4')
        ->and(GapScale::classFor('huge', 3))->toBe('gap-3')
        ->and(GapScale::classFor(''))->toBe('gap-4');
});

test('the step is exposed for a caller that needs the number', function () {
    expect(GapScale::step('lg'))->toBe(6)
        ->and(GapScale::step('nonsense', 2))->toBe(2);
});

// ─── Flex delegates, and did not move ──────────────────────────────

// Flex owned this map first; it now delegates. These are the answers it gave
// before, including the one that used to fall through to the default arm.
test('Flex still resolves every step exactly as it did', function () {
    foreach (range(0, 12) as $step) {
        expect((new Flex)->gap($step)->getGapClass())->toBe("gap-{$step}");
    }
});

test('Flex keeps its own default of 4', function () {
    expect((new Flex)->getGapClass())->toBe('gap-4')
        ->and((new Flex)->gap(99)->getGapClass())->toBe('gap-12');
});
