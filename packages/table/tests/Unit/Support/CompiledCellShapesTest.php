<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\View\Skeleton;
use NyonCode\WireTable\Support\TableGestures;
use NyonCode\WireTable\Table;

/*
 * The three compiled cells nothing was looking at.
 *
 * A `Skeleton` is rendered once per table and spliced per row, so every condition
 * that varies per *table* is decided at compile time and frozen into the markup.
 * That makes the compiled shape a PHP decision with a browser-only symptom — the
 * pattern that had already produced findings in the action cell and the stacked
 * card, and the reason these three were listed as the ones left.
 *
 * Their shape *keys* turned out to be guarded (memoising the sub-row cell flat
 * instead of per shape fails two existing tests). Their baked *conditions* were
 * not: hardcoding the selection cell's range-selection flag, its density, or the
 * sub-row cell's border each passed all 2302 tests in the package.
 *
 * The range-selection flag is the one that matters. It writes the click handler:
 *
 *   x-on:click="{{ $usesRangeSelection ? '$event.shiftKey || … || ' : '' }}toggle(key)"
 *
 * With ranges ON a modified click short-circuits before `toggle()`, because the
 * row controller answers Shift and mod for the whole row. Baked the wrong way, a
 * Shift+click on the selection cell would be answered twice — the range extends
 * AND that one row toggles — which is the same "one gesture, two answers" failure
 * the mobile action collapse clones away from.
 */

/** Selectable, since the range gesture is gated on it. */
function shapesTable(): Table
{
    return Table::make()->selectable();
}

// ─── The selection cell ──────────────────────────────────────────────────────

it('leaves a modified click to the row controller while ranges are on', function () {
    $html = shapesTable()->gestures(TableGestures::all())->getSelectionCellSkeleton()->toHtml();

    // The modifier check runs first and short-circuits, so toggle() never fires
    // for a Shift/mod click — the row controller owns those.
    expect($html)->toContain('$event.shiftKey || $event.ctrlKey || $event.metaKey || toggle(');
});

it('takes every click itself when ranges are off', function () {
    $html = shapesTable()->gestures(TableGestures::none())->getSelectionCellSkeleton()->toHtml();

    // Nobody else would answer a modified click here, so the cell answers all of them.
    expect($html)->toContain('toggle(')
        ->not->toContain('$event.shiftKey');
});

it('needs a selectable table for the range gesture at all', function () {
    // usesRangeSelection() is gated on isSelectable(); a table with the gesture on
    // but no selection column has no range to extend.
    expect(Table::make()->gestures(TableGestures::all())->usesRangeSelection())->toBeFalse()
        ->and(shapesTable()->gestures(TableGestures::all())->usesRangeSelection())->toBeTrue();
});

it('bakes the table density into the selection cell', function () {
    expect(shapesTable()->getSelectionCellSkeleton()->toHtml())->toContain('px-6 py-4')
        ->and(shapesTable()->compact()->getSelectionCellSkeleton()->toHtml())->toContain('px-4 py-2');
});

it('leaves one hole in the selection cell, for the record key in both encodings', function () {
    $table = shapesTable();
    $skeleton = $table->getSelectionCellSkeleton();

    expect($skeleton->toHtml())->toContain(Skeleton::slot('keyJs'))
        ->toContain(Skeleton::slot('key'))
        // Compiled once per table — the row loop splices, it does not render.
        ->and($table->getSelectionCellSkeleton())->toBe($skeleton);

    $filled = $skeleton->fill(['keyJs' => "'7'", 'key' => '7']);

    expect($filled)->toContain('wire:key="sel-7"')
        ->toContain("toggle('7')")
        ->not->toContain(Skeleton::slot('keyJs'));
});

// ─── The sub-row expander cell ───────────────────────────────────────────────

it('compiles one sub-row cell per state, not one per table', function () {
    $table = shapesTable();

    $none = $table->getSubRowCell("'1'", '1', hasToggle: false, isExpanded: false);
    $collapsed = $table->getSubRowCell("'1'", '1', hasToggle: true, isExpanded: false);
    $expanded = $table->getSubRowCell("'1'", '1', hasToggle: true, isExpanded: true);

    // Three shapes, three different cells — a flat memo would serve the first to all.
    expect($none)->not->toBe($collapsed)
        ->and($collapsed)->not->toBe($expanded)
        // A record with no children still gets the cell, or the columns stop lining up.
        ->and($none)->toContain('<td');
});

it('bakes the table density and border into the sub-row cell', function () {
    $plain = shapesTable()->getSubRowCell("'1'", '1', true, false);
    $dense = shapesTable()->compact()->bordered()->getSubRowCell("'1'", '1', true, false);

    expect($plain)->toContain('px-6 py-4')
        ->not->toContain('border border-gray-200')
        ->and($dense)->toContain('px-4 py-2')
        ->toContain('border border-gray-200 dark:border-gray-700');
});

it('splices the record key into the sub-row cell in both encodings', function () {
    $cell = shapesTable()->getSubRowCell("'a&b'", 'a&amp;b', true, false);

    expect($cell)->toContain('a&amp;b')
        ->toContain("'a&b'")
        ->not->toContain(Skeleton::slot('keyJs'));
});

// ─── The teleported context-menu panel ───────────────────────────────────────

it('compiles the context-menu scaffolding once, with two holes', function () {
    $table = shapesTable();
    $skeleton = $table->getRowContextMenuSkeleton();

    expect($skeleton->toHtml())->toContain(Skeleton::slot('key'))
        ->toContain(Skeleton::slot('menu'))
        // One shape for the whole table: the panel holds no per-row Alpine state,
        // because one controller on the <tbody> drives every row's menu by key.
        ->and($table->getRowContextMenuSkeleton())->toBe($skeleton);

    expect($skeleton->fill(['key' => '7', 'menu' => '<li>Edit</li>']))
        ->toContain('<li>Edit</li>')
        ->toContain('7');
});
