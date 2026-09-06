<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\Action;
use NyonCode\WireTable\Support\StickyColumn;
use NyonCode\WireTable\Table;

/*
 * The owner of "this column stays put while the rest scrolls".
 *
 * Everything it returns is a literal utility string, and that is the constraint
 * worth a test of its own: a class that is only ever assembled by string
 * manipulation appears in no source file, so Tailwind's extractor never emits
 * it and the pinning silently does nothing in a built stylesheet. The
 * assertions below are therefore about exact strings, not about shape.
 */

it('pins to the side the column already sits on', function () {
    expect(StickyColumn::on('end')->cellClass)
        ->toContain('sticky right-0')
        ->toContain('border-l')
        ->and(StickyColumn::on('start')->cellClass)
        ->toContain('sticky left-0')
        ->toContain('border-r');
});

it('gives a header cell a higher tier than a body cell', function () {
    $sticky = StickyColumn::on('end');

    // Both stay under the sticky <thead>'s own z-10 stacking context, so a
    // pinned body cell cannot ride over the pinned header when both axes are
    // scrolled; within the header row, the pinned cell still has to beat its
    // siblings.
    expect($sticky->cellClass)->toContain('z-[1]')
        ->and($sticky->headerCellClass)->toContain('z-10')
        ->and($sticky->headerCellClass)->not->toContain('z-[1]');
});

it('carries bg-inherit on the cell, which is what the top layer inherits back', function () {
    // The cell's own background does no visual work — it is the middle link in
    // the chain row → cell → layer, and without it the layer inherits the
    // transparent default and the pinning shows through.
    expect(StickyColumn::on('end')->cellClass)->toContain('bg-inherit');
});

it('renders the surface and the inheriting layer, in that order', function () {
    $layers = StickyColumn::on('end')->layers();

    expect($layers)->toContain('bg-white dark:bg-gray-800')
        ->and($layers)->toContain('bg-inherit')
        ->and(mb_strpos($layers, 'bg-white'))->toBeLessThan((int) mb_strpos($layers, 'absolute inset-0 bg-inherit'))
        // Spliced into a cell that is emitted once per row, so the two tags touch.
        ->and($layers)->not->toContain('></div> <div');
});

it('renders its layers once', function () {
    $sticky = StickyColumn::on('end');

    expect($sticky->layers())->toBe($sticky->layers());
});

it('reports nothing at all when the column is not pinned', function () {
    $none = StickyColumn::none();

    expect($none->isPinned)->toBeFalse()
        ->and($none->cellClass)->toBe('')
        ->and($none->headerCellClass)->toBe('')
        ->and($none->surfaceClass)->toBe('')
        ->and($none->side)->toBe('')
        // Echoed unguarded by five different cells, so it has to be a string.
        ->and($none->layers())->toBe('');
});

it('reads the pinning off the table, and the side off the actions position', function () {
    $end = Table::make()->actions([Action::make('open')])->stickyActions();
    $start = Table::make()->actions([Action::make('open')])->actionsPosition('start')->stickyActions();

    expect(StickyColumn::forActions($end)->side)->toBe('end')
        ->and(StickyColumn::forActions($start)->side)->toBe('start')
        ->and(StickyColumn::forActions($end)->isPinned)->toBeTrue();
});

it('is not pinned by default, and not pinned once it is turned back off', function () {
    $table = Table::make()->actions([Action::make('open')]);

    expect($table->hasStickyActions())->toBeFalse()
        ->and(StickyColumn::forActions($table)->isPinned)->toBeFalse()
        ->and(StickyColumn::forActions($table->stickyActions()->stickyActions(false))->isPinned)->toBeFalse();
});

it('refuses to claim a pinned column on a table that renders no actions column', function () {
    // The actions column only exists when there are actions; a plan that claimed
    // otherwise would hand five views classes for a cell none of them draws.
    $table = Table::make()->stickyActions();

    expect($table->hasStickyActions())->toBeTrue()
        ->and(StickyColumn::forActions($table)->isPinned)->toBeFalse();
});
