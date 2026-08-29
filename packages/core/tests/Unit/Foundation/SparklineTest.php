<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\View\Sparkline;

/*
 * The sparkline geometry, which was an `@php` block inside a widget template.
 *
 * Nothing could reach it and nothing did test it, so three cases had been wrong
 * for as long as the template existed — all three visible only to someone
 * looking at the chart:
 *
 *  1. A series topping out at exactly zero was squashed and never reached the
 *     top of the box. `$max = max($data) ?: 1` is a divide-by-zero guard on a
 *     value that is not the divisor; it rewrote a maximum of 0 as 1 and stretched
 *     the range by that much. A burndown reaching its target is exactly this.
 *  2. A flat series was drawn along the floor, so a steady figure read as one
 *     that had fallen to zero.
 *  3. A single reading emitted a one-point `<polyline>`, which draws nothing.
 *
 * The mapping is otherwise unchanged, so every existing widget draws the curve it
 * drew before — pinned by the first test here.
 */

/** @return array<int, array{float, float}> the points, parsed back into pairs */
function sparkPoints(Sparkline $s): array
{
    return array_map(
        fn (string $pair): array => array_map(floatval(...), explode(',', $pair)),
        explode(' ', $s->points()),
    );
}

it('maps the highest value to the top and the lowest to the floor', function () {
    $spark = Sparkline::of([1, 5, 3]);

    // Unchanged from the template this replaced: 28 units of travel between
    // TOP (2) and HEIGHT (30), a reading every 10 across.
    expect($spark)->not->toBeNull()
        ->and($spark->points())->toBe('0,30 10,2 20,16')
        ->and($spark->viewBox())->toBe('0 0 20 30');
});

it('reaches the top when the series tops out at zero', function () {
    // A burndown that hits its target. The maximum IS the top of the box, so the
    // last point belongs at TOP — it used to land at 6.67, short of it, because
    // the range had been stretched from 5 to 6.
    $spark = Sparkline::of([-5, -2, 0]);

    expect(sparkPoints($spark)[2][1])->toBe(2.0)
        ->and($spark->points())->toBe('0,30 10,13.2 20,2');
});

it('levels a flat series through the middle, not along the floor', function () {
    // Every value equal: no curve to draw, but "steady" and "fallen to zero" are
    // different readings and the floor says the second one.
    foreach ([[4, 4, 4], [0, 0, 0], [-3, -3]] as $flat) {
        $spark = Sparkline::of($flat);

        foreach (sparkPoints($spark) as [$x, $y]) {
            expect($y)->toBe(16.0);
        }
    }
});

it('draws a single reading as a line rather than a dot', function () {
    // One point is not a polyline: the browser draws nothing at all for it.
    $spark = Sparkline::of([7]);

    expect(sparkPoints($spark))->toHaveCount(2)
        ->and($spark->points())->toBe('0,16 10,16')
        // …and it still spans a box, so the svg is not zero-wide.
        ->and($spark->viewBox())->toBe('0 0 10 30')
        ->and($spark->width())->toBe(10.0);
});

it('has nothing to draw for an empty series', function () {
    expect(Sparkline::of([]))->toBeNull();
});

it('keeps the coordinates short', function () {
    // One row of one table draws one of these; three of them draw three. A
    // coordinate carrying seventeen significant figures is bytes on every one.
    $spark = Sparkline::of([0, 1, 2]);

    expect($spark->points())->toBe('0,30 10,16 20,2')
        ->and(Sparkline::of([0, 3, 7])->points())->toBe('0,30 10,18 20,2');
});

it('plots negative and fractional series the same way', function () {
    $spark = Sparkline::of([-1.5, 0.5]);

    // Nothing special about the sign: the series is normalised over its own range.
    expect($spark->points())->toBe('0,30 10,2');
});

it('reindexes a series that arrives with gaps in its keys', function () {
    // array_filter() over a caller's series leaves holes; the x coordinate is the
    // reading's position, not its key, or a filtered series would draw off-box.
    $spark = Sparkline::of([0 => 1, 3 => 5, 7 => 3]);

    expect($spark->points())->toBe('0,30 10,2 20,16');
});
