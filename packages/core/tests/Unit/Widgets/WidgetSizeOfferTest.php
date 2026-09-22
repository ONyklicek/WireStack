<?php

declare(strict_types=1);

use NyonCode\WireCore\Widgets\Stat;
use NyonCode\WireCore\Widgets\StatsOverviewWidget;
use NyonCode\WireCore\Widgets\Support\WidgetSizeOffer;

/**
 * Which sizes a widget may be given, and what each resize button does next.
 *
 * Two bounds, one owner, because they are the same question asked twice: a
 * widget's own `sizes()` — documented as narrowing the offer since it shipped,
 * and read by nothing until this class — and the grid it is on, where a tile
 * wider than the dashboard does not clip but makes CSS Grid add a column and
 * squeezes every other tile into what is left.
 */
function sizeWidget(array $sizes = []): StatsOverviewWidget
{
    $widget = StatsOverviewWidget::make()->key('w')->stats([Stat::make('A', '1')]);

    return $sizes === [] ? $widget : $widget->sizes($sizes);
}

// ─── Nothing declared: the whole grid, and no more of it than there is ───────

it('offers the whole grid to a widget that declares nothing', function () {
    $offer = WidgetSizeOffer::for(sizeWidget(), 3);

    expect($offer->allows(3, 6))->toBeTrue()
        ->and($offer->allows(1, 1))->toBeTrue()
        // Four columns on a three-column dashboard is the invented track.
        ->and($offer->allows(4, 1))->toBeFalse();
});

it('steps by one in each direction, and stops at the grid', function () {
    $offer = WidgetSizeOffer::for(sizeWidget(), 2);

    expect($offer->wider(1, 1))->toBe([2, 1])
        ->and($offer->wider(2, 1))->toBeNull()
        ->and($offer->narrower(2, 1))->toBe([1, 1])
        ->and($offer->narrower(1, 1))->toBeNull()
        ->and($offer->taller(1, 1))->toBe([1, 2])
        ->and($offer->taller(1, 6))->toBeNull()
        ->and($offer->shorter(1, 3))->toBe([1, 2])
        ->and($offer->shorter(1, 1))->toBeNull();
});

it('snaps a size the browser sent to one the grid can draw', function () {
    $offer = WidgetSizeOffer::for(sizeWidget(), 2);

    expect($offer->nearest(99, 99))->toBe([2, 6])
        ->and($offer->nearest(4, 1))->toBe([2, 1])
        ->and($offer->nearest(-3, 0))->toBe([1, 1]);
});

// ─── Declared sizes: the offer is the declaration ────────────────────────────

it('holds a widget to the sizes it declared', function () {
    $offer = WidgetSizeOffer::for(sizeWidget([[2, 1], [4, 2]]), 4);

    expect($offer->allows(2, 1))->toBeTrue()
        ->and($offer->allows(4, 2))->toBeTrue()
        // On the grid, but not on the offer — which is the whole point of
        // declaring one.
        ->and($offer->allows(1, 1))->toBeFalse()
        ->and($offer->allows(4, 1))->toBeFalse();
});

it('walks the declared widths, taking the height that width is offered at', function () {
    // Otherwise `[[2, 1], [4, 2]]` would offer a size no button could reach: at
    // 2×1 nothing else is one row tall, so a strict "same height" step would
    // leave the second pair unreachable.
    $offer = WidgetSizeOffer::for(sizeWidget([[2, 1], [4, 2]]), 4);

    expect($offer->wider(2, 1))->toBe([4, 2])
        ->and($offer->narrower(4, 2))->toBe([2, 1])
        ->and($offer->wider(4, 2))->toBeNull();
});

it('walks heights at the current width, so a height button never moves a tile sideways', function () {
    $offer = WidgetSizeOffer::for(sizeWidget([[2, 1], [2, 3], [4, 2]]), 4);

    expect($offer->taller(2, 1))->toBe([2, 3])
        ->and($offer->shorter(2, 3))->toBe([2, 1])
        // Nothing else at four columns: a dead end rather than a jump to two.
        ->and($offer->taller(4, 2))->toBeNull();
});

it('takes a step at a time rather than jumping to the far end', function () {
    $offer = WidgetSizeOffer::for(sizeWidget([[1, 1], [2, 1], [4, 1]]), 4);

    expect($offer->wider(1, 1))->toBe([2, 1])
        ->and($offer->wider(2, 1))->toBe([4, 1]);
});

it('clamps a declared size to the grid instead of dropping it', function () {
    // The declaration was written against a dashboard whose column count can
    // change afterwards. A widget offering nothing at all would be a tile with
    // four disabled buttons and no way to tell why.
    $offer = WidgetSizeOffer::for(sizeWidget([[4, 2]]), 2);

    expect($offer->arrivalSize())->toBe([2, 2])
        ->and($offer->allows(2, 2))->toBeTrue();
});

it('collapses two declared sizes that clamp onto each other', function () {
    // [[3, 1], [4, 1]] on a two-column grid is one size twice, and a duplicate
    // would make a button appear to do nothing.
    $offer = WidgetSizeOffer::for(sizeWidget([[3, 1], [4, 1]]), 2);

    expect($offer->wider(2, 1))->toBeNull()
        ->and($offer->arrivalSize())->toBe([2, 1]);
});

it('arrives at the first size declared, which is what the tray promises', function () {
    $offer = WidgetSizeOffer::for(sizeWidget([[2, 2], [1, 1]]), 4);

    expect($offer->arrivalSize())->toBe([2, 2]);

    // And one column by one row when nothing is declared, as it always was.
    expect(WidgetSizeOffer::for(sizeWidget(), 4)->arrivalSize())->toBe([1, 1]);
});
