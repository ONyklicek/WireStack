<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Fieldset;
use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Section;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/*
 * A span, drawn against the grid it lands in.
 *
 * Nothing measured this pair before, and it had drifted in three directions at
 * once: every item emitted `sm:col-span-N` from one fixed map, while the grids
 * around them ramped at `md` (`ResponsiveGrid::cols()`) or at `sm` (five local
 * copies of a `match`), and a form field's wrapper had a third map that stopped
 * at two columns.
 *
 * The failure is silent by construction. CSS Grid does not clip an item wider
 * than its grid — it **adds** the missing track — so an over-wide span renders a
 * page that still looks like a page, with every other item on the row squeezed
 * into the remainder. Measured in a browser at 700px before this landed: a
 * Section declaring one column until `md` drew two, 494px and 510px wide.
 *
 * So the assertions below are about which classes exist together, and the
 * browser half is `workbench/scripts/verify-grid-spans.mjs`, which measures the
 * rendered track count against what the layout declared.
 */

function renderLayout(object $layout): string
{
    return (string) $layout->render();
}

it('steps a span with the grid a Section builds', function () {
    // `cols(2)` is one column until `md`, so a span of two starts at `md` —
    // never at `sm`, where the column it asks for does not exist yet.
    $html = renderLayout(Section::make('Profile')->columns(2)->schema([
        TextEntry::make('wide')->columnSpan(2),
    ]));

    expect($html)->toContain('md:col-span-2')
        ->and($html)->not->toContain('sm:col-span-2');
});

it('caps a span at the columns the grid actually has', function () {
    // Four columns asked for, two declared. The grid never has four, so neither
    // does the item — otherwise it invents the other two.
    $html = renderLayout(Grid::make()->columns(2)->schema([
        TextEntry::make('greedy')->columnSpan(4),
    ]));

    expect($html)->toContain('md:col-span-2')
        ->and($html)->not->toContain('col-span-3')
        ->and($html)->not->toContain('col-span-4');
});

it('follows the field ladder inside a Fieldset, which ramps earlier', function () {
    // A field reads at half a phone's width, so a fieldset is two columns from
    // `sm` and three from `md` — and a span of three has to say both.
    $html = renderLayout(Fieldset::make('Address')->columns(3)->schema([
        TextEntry::make('street')->columnSpan(3),
    ]));

    expect($html)->toContain('sm:col-span-2')
        ->and($html)->toContain('md:col-span-3')
        // The grid it is drawn in, from the same owner.
        ->and($html)->toContain('sm:grid-cols-2')
        ->and($html)->toContain('md:grid-cols-3');
});

it('spans the whole row wherever the row ends, at every width', function () {
    $html = renderLayout(Grid::make()->columns(3)->schema([
        TextEntry::make('banner')->columnSpanFull(),
    ]));

    // Grid-aware by construction: it spans the explicit tracks there are, so it
    // is the one span that needs no breakpoint at all.
    expect($html)->toContain('col-span-full')
        ->and($html)->not->toContain('md:col-span-3');
});

it('tells an infolist entry the grid the infolist declared', function () {
    $html = Infolist::make()
        ->columns(3)
        ->schema([TextEntry::make('summary')->columnSpan(3)])
        ->toHtml();

    expect($html)->toContain('md:col-span-3')
        ->and($html)->not->toContain('sm:col-span-3');
});

it('leaves a one-column grid without a span class to disagree with', function () {
    $html = renderLayout(Grid::make()->columns(1)->schema([
        TextEntry::make('only')->columnSpan(2),
    ]));

    expect($html)->not->toContain('col-span-2');
});
