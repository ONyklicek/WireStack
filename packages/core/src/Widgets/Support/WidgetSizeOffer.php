<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Support;

use NyonCode\WireCore\Widgets\Widget;

/**
 * Which sizes one widget may be given on one grid — and what each of the four
 * resize buttons does next.
 *
 * `Widget::sizes()` has always been documented as narrowing what a widget is
 * offered at: "a sparkline row is not a 1×1 tile and a single figure is not a
 * 4×6 one, and letting a user find that out by dragging is worse than not
 * offering it". Nothing read it. `getSizes()` had no caller anywhere in the
 * repository, only `getDefaultSize()` reached past it for the first pair, and
 * the steppers walked the whole grid — so a widget declaring one size could be
 * stepped to any of twenty-four, which is precisely the discovery the
 * declaration exists to prevent.
 *
 * The other half is the grid. A dashboard declaring three columns still offered
 * a fourth, because the buttons were bounded by the widest grid there is rather
 * than by the grid they are on — and a tile wider than its grid does not clip,
 * it *adds a column*, squeezing every other tile on the dashboard into whatever
 * is left. Both bounds are this class's answer, because they are the same
 * question asked twice.
 *
 * ## What the four buttons do
 *
 * The width buttons walk the offered **widths**, adopting the height that width
 * is offered at — otherwise a declaration of `[[2, 1], [4, 2]]` would offer a
 * size no button could ever reach. The height buttons walk the heights offered
 * **at the current width**, so a height button never changes the width under
 * the reader's hand. With nothing declared both are a plain ±1 inside the grid,
 * which is what every dashboard did before this existed.
 *
 * A button with nowhere to go answers null, and the view draws it disabled —
 * the one place a user can see the offer.
 */
final class WidgetSizeOffer
{
    /** The tallest a tile may be, matching what {@see WidgetLayout} will store. */
    public const MAX_HEIGHT = 6;

    /**
     * @param  array<int, array{0: int, 1: int}>  $offered  In declaration order; the first is the arrival size
     */
    private function __construct(private readonly array $offered) {}

    /**
     * The sizes this widget may take on a grid of `$columns` columns.
     *
     * A declared pair outside the grid is clamped rather than dropped: the
     * declaration was written against a dashboard whose column count can change
     * afterwards, and a widget that offered nothing at all would be a tile with
     * four disabled buttons and no way to tell why.
     */
    public static function for(Widget $widget, int $columns): self
    {
        $maxWidth = max(1, min(4, $columns));
        $declared = [];

        foreach ($widget->getSizes() as [$width, $height]) {
            $pair = [self::clamp($width, $maxWidth), self::clamp($height, self::MAX_HEIGHT)];

            // Two declared pairs can clamp onto each other — [[3, 1], [4, 1]] on
            // a two-column grid is one size twice — and a duplicate would make a
            // button appear to do nothing.
            if (! in_array($pair, $declared, true)) {
                $declared[] = $pair;
            }
        }

        if ($declared !== []) {
            return new self($declared);
        }

        // Nothing declared: the whole grid, which is what the docs promise and
        // what every dashboard had before `sizes()` existed.
        $free = [];

        for ($width = 1; $width <= $maxWidth; $width++) {
            for ($height = 1; $height <= self::MAX_HEIGHT; $height++) {
                $free[] = [$width, $height];
            }
        }

        return new self($free);
    }

    /** Whether this exact size is on the offer. */
    public function allows(int $width, int $height): bool
    {
        return in_array([$width, $height], $this->offered, true);
    }

    /**
     * The offered size closest to the one asked for.
     *
     * Every resize arrives from the browser, so "closest" rather than "refused":
     * a stored layout can outlive the declaration that shaped it, and a widget
     * that simply stopped responding to its own buttons would be worse than one
     * that lands on the nearest size it has. Width is weighed first — it is what
     * the grid is built out of, and a tile of the wrong width displaces its
     * neighbours while one of the wrong height does not.
     *
     * @return array{0: int, 1: int}
     */
    public function nearest(int $width, int $height): array
    {
        $best = $this->offered[0];
        $bestDistance = null;

        foreach ($this->offered as [$offeredWidth, $offeredHeight]) {
            $distance = abs($offeredWidth - $width) * 10 + abs($offeredHeight - $height);

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = [$offeredWidth, $offeredHeight];
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * The size a widget arrives at when it is dropped in from the tray: the
     * first it offers, clamped to this grid.
     *
     * @return array{0: int, 1: int}
     */
    public function arrivalSize(): array
    {
        return $this->offered[0];
    }

    /** @return array{0: int, 1: int}|null */
    public function wider(int $width, int $height): ?array
    {
        return $this->stepWidth($width, $height, wider: true);
    }

    /** @return array{0: int, 1: int}|null */
    public function narrower(int $width, int $height): ?array
    {
        return $this->stepWidth($width, $height, wider: false);
    }

    /** @return array{0: int, 1: int}|null */
    public function taller(int $width, int $height): ?array
    {
        return $this->stepHeight($width, $height, taller: true);
    }

    /** @return array{0: int, 1: int}|null */
    public function shorter(int $width, int $height): ?array
    {
        return $this->stepHeight($width, $height, taller: false);
    }

    /**
     * The next offered width in one direction, with the height that width is
     * offered at — the current one where it is available, the closest otherwise.
     *
     * @return array{0: int, 1: int}|null
     */
    private function stepWidth(int $width, int $height, bool $wider): ?array
    {
        $target = null;

        foreach ($this->offered as [$offeredWidth]) {
            if ($wider ? $offeredWidth <= $width : $offeredWidth >= $width) {
                continue;
            }

            // The *nearest* width in that direction, not the widest: a button is
            // a step, and one that jumped from two columns to four would skip a
            // size the widget declared.
            if ($target === null || ($wider ? $offeredWidth < $target : $offeredWidth > $target)) {
                $target = $offeredWidth;
            }
        }

        return $target === null ? null : [$target, $this->heightFor($target, $height)];
    }

    /**
     * The next offered height at this width, in one direction.
     *
     * @return array{0: int, 1: int}|null
     */
    private function stepHeight(int $width, int $height, bool $taller): ?array
    {
        $target = null;

        foreach ($this->offered as [$offeredWidth, $offeredHeight]) {
            if ($offeredWidth !== $width) {
                continue;
            }

            if ($taller ? $offeredHeight <= $height : $offeredHeight >= $height) {
                continue;
            }

            if ($target === null || ($taller ? $offeredHeight < $target : $offeredHeight > $target)) {
                $target = $offeredHeight;
            }
        }

        return $target === null ? null : [$width, $target];
    }

    /** The height to take a given width at: the one in hand if it is offered there, else the closest. */
    private function heightFor(int $width, int $height): int
    {
        $best = null;
        $bestDistance = null;

        foreach ($this->offered as [$offeredWidth, $offeredHeight]) {
            if ($offeredWidth !== $width) {
                continue;
            }

            if ($offeredHeight === $height) {
                return $height;
            }

            $distance = abs($offeredHeight - $height);

            if ($bestDistance === null || $distance < $bestDistance) {
                $best = $offeredHeight;
                $bestDistance = $distance;
            }
        }

        return $best ?? $height;
    }

    private static function clamp(int $value, int $max): int
    {
        return max(1, min($max, $value));
    }
}
