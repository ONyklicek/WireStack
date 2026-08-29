<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\View;

/**
 * A series of numbers as one SVG polyline — the geometry, not the markup.
 *
 * This used to be an `@php` block inside `widgets/stats-overview.blade.php`:
 * min, max, range and the coordinate mapping computed in a template. That put
 * arithmetic where nothing can test it and where nothing else can reach it, and
 * it is the rule `AI_CHANGE_PROTOCOL.md` states outright — resolve state in PHP,
 * render markup in Blade. The widget view now asks this and draws the result;
 * `WireTable\Columns\MetricColumn` asks the same thing for a table cell.
 *
 * ## The box
 *
 * A point every {@see STEP} units across, {@see HEIGHT} tall, with the highest
 * value at {@see TOP} and the lowest at the floor. The mapping is unchanged from
 * the template it replaces, so every existing widget draws exactly the curve it
 * drew before — except in the three cases below, where it drew the wrong one.
 *
 * ## What a flat series looks like
 *
 * A series with no variation — every value equal, or a single reading — has no
 * curve to draw. The template mapped it to the **floor**, so a steady figure
 * read as a figure that had fallen to zero, and a single reading emitted a
 * one-point `<polyline>`, which draws nothing at all. Both are drawn here as a
 * flat line through the vertical **centre**: level, and visibly present.
 *
 * ## Why the zero guard moved
 *
 * The template read `$max = max($data) ?: 1` — a divide-by-zero guard applied to
 * a value that is not the divisor. The divisor is the *range*, which had its own
 * guard already; all `?: 1` did was rewrite a maximum of exactly 0 as 1 and
 * stretch the range by that much. So any series topping out at zero — a burndown
 * reaching its target, a balance paid off — was squashed and never reached the
 * top of the box. The guard belongs to the range, and that is where it is.
 */
final class Sparkline
{
    /** Horizontal distance between two readings. */
    public const STEP = 10;

    /** Height of the box the curve is drawn in. */
    public const HEIGHT = 30;

    /** Where the highest value sits — not 0, so the stroke stays inside the box. */
    public const TOP = 2.0;

    private function __construct(
        /** @var array<int, array{float, float}> */
        private readonly array $points,
        private readonly float $width,
    ) {}

    /**
     * Plot a series, or null when there is nothing to plot.
     *
     * @param  array<int, int|float>  $values  in reading order, oldest first
     */
    public static function of(array $values): ?self
    {
        $values = array_values($values);

        if ($values === []) {
            return null;
        }

        $min = (float) min($values);
        $max = (float) max($values);
        $range = $max - $min;

        // A single reading has no width to span, so it is given the same width a
        // pair would have: a flat line has to be a line, not a dot.
        $steps = max(count($values) - 1, 1);
        $width = (float) ($steps * self::STEP);

        $floor = self::HEIGHT;
        $travel = $floor - self::TOP;
        $middle = self::TOP + $travel / 2;

        $points = [];

        foreach ($values as $i => $value) {
            $points[] = [
                (float) ($i * self::STEP),
                // No variation means no curve; level it through the middle rather
                // than along the floor, which reads as zero.
                $range == 0.0 ? $middle : $floor - (((float) $value - $min) / $range * $travel),
            ];
        }

        if (count($points) === 1) {
            $points[] = [$width, $points[0][1]];
        }

        return new self($points, $width);
    }

    /**
     * The `points` attribute of an SVG `<polyline>`.
     *
     * Rounded, because a coordinate carrying seventeen significant figures is
     * bytes on every row of every table that draws one.
     */
    public function points(): string
    {
        return implode(' ', array_map(
            fn (array $point): string => $this->coordinate($point[0]).','.$this->coordinate($point[1]),
            $this->points,
        ));
    }

    /** The `viewBox` the polyline is drawn in. */
    public function viewBox(): string
    {
        return '0 0 '.$this->coordinate($this->width).' '.self::HEIGHT;
    }

    public function width(): float
    {
        return $this->width;
    }

    private function coordinate(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
