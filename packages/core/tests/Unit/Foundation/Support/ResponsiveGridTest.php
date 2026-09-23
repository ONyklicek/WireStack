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

    // 6 breakpoints (base + sm/md/lg/xl/2xl) × 12 counts, twice over —
    // grid-cols for the grid and col-span for the items in it — plus
    // `col-span-full`.
    expect($classes)->toHaveCount(145)
        ->toContain('grid-cols-1')
        ->toContain('lg:grid-cols-3')
        ->toContain('2xl:grid-cols-12');

    // Everything cols() can emit must appear in the literal allowlist.
    expect($classes)->toContain(ResponsiveGrid::cols(['xl' => 7]));
});

// ─── Spans, resolved against the grid they are drawn in ──────────────────────

it('steps a span with the grid rather than stating one number', function () {
    // The grid this is drawn in has one column, then two from md, then three
    // from xl. A tile asking for three can only have two while the grid has
    // two — and one on a phone, where a bare `col-span-3` would make CSS Grid
    // invent the two columns it does not have.
    $ladder = ['default' => 1, 'md' => 2, 'xl' => 3];

    expect(ResponsiveGrid::span(3, $ladder))->toBe('md:col-span-2 xl:col-span-3')
        // Two columns is already right from md up, so there is nothing to say
        // at xl: the shortest correct answer.
        ->and(ResponsiveGrid::span(2, $ladder))->toBe('md:col-span-2')
        // More than the grid ever has is the grid, not an invented track.
        ->and(ResponsiveGrid::span(9, $ladder))->toBe('md:col-span-2 xl:col-span-3');
});

it('says nothing for a span that is one column, and passes `full` through', function () {
    expect(ResponsiveGrid::span(1, 3))->toBe('')
        ->and(ResponsiveGrid::span(null, 3))->toBe('')
        ->and(ResponsiveGrid::span(0, 3))->toBe('')
        // Grid-aware by construction: it spans the explicit tracks there are, at
        // every width, and can never conjure one.
        ->and(ResponsiveGrid::span('full', 3))->toBe('col-span-full');
});

it('reads an int the same way cols() does', function () {
    // cols(3) is one column until md and three from it, so a span of 3 belongs
    // to md and nowhere below it.
    expect(ResponsiveGrid::cols(3))->toBe('grid-cols-1 md:grid-cols-3')
        ->and(ResponsiveGrid::span(3, 3))->toBe('md:col-span-3')
        // A one-column grid can hold nothing wider than one column.
        ->and(ResponsiveGrid::span(2, 1))->toBe('');
});

it('orders the ladder by width, not by how the map was written', function () {
    // "Has this changed since the breakpoint below?" is only answerable in
    // min-width order, and a map is written in whatever order somebody typed.
    expect(ResponsiveGrid::span(4, ['xl' => 4, 'default' => 1, 'md' => 2]))
        ->toBe('md:col-span-2 xl:col-span-4');
});

it('offers the field ladder every form surface is built from', function () {
    // A field reads at half a phone's width, so this ramps at `sm` — the ladder
    // a step, a tab, a fieldset, a record panel and a repeatable entry each had
    // their own copy of.
    expect(ResponsiveGrid::fieldColumns(4))->toBe(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4])
        ->and(ResponsiveGrid::cols(ResponsiveGrid::fieldColumns(4)))
        ->toBe('grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4')
        ->and(ResponsiveGrid::fieldColumns(1))->toBe(['default' => 1])
        ->and(ResponsiveGrid::fieldColumns(9))->toBe(['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4]);

    // And a span drawn in it steps the same way, which is the whole reason the
    // ladder is named rather than written out per view.
    expect(ResponsiveGrid::span(4, ResponsiveGrid::fieldColumns(4)))
        ->toBe('sm:col-span-2 md:col-span-3 lg:col-span-4');
});

it('offers the card ladder the widget grid is built from', function () {
    // A card is not a form field: three of them at 640px are three columns of
    // nothing, so this ramps at md and then at xl.
    expect(ResponsiveGrid::cardColumns(3))->toBe(['default' => 1, 'md' => 2, 'xl' => 3])
        ->and(ResponsiveGrid::cols(ResponsiveGrid::cardColumns(3)))
        ->toBe('grid-cols-1 md:grid-cols-2 xl:grid-cols-3')
        ->and(ResponsiveGrid::cardColumns(1))->toBe(['default' => 1])
        // Beyond four a card has no room left.
        ->and(ResponsiveGrid::cardColumns(9))->toBe(['default' => 1, 'md' => 2, 'xl' => 4]);
});

it('keeps every span it can emit inside the scannable allowlist', function () {
    $classes = ResponsiveGrid::scannableClasses();

    foreach ([2, 3, 4] as $span) {
        foreach (explode(' ', ResponsiveGrid::span($span, ResponsiveGrid::cardColumns(4))) as $class) {
            expect($classes)->toContain($class);
        }
    }

    expect($classes)->toContain('col-span-full');
});
