<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets\Support;

use NyonCode\WireCore\Widgets\Widget;

/**
 * What one user did to one dashboard: which widgets are on it, in what order,
 * and how big each one is.
 *
 * A value object rather than a few lines inside `WithWidgets`, because deciding
 * what a stored layout means is *business logic* and the coding standard keeps
 * that out of traits. It is also the piece with all the edges — every rule below
 * exists because the alternative is a dashboard that renders wrong rather than
 * one that renders differently.
 *
 * ## The stored shape
 *
 * ```php
 * ['widgets' => [
 *     ['key' => 'revenue', 'w' => 2, 'h' => 1],
 *     ['key' => 'orders',  'w' => 1, 'h' => 2],
 * ]]
 * ```
 *
 * An ordered list, not a map of coordinates. Position comes from the order and
 * the size from two integers, and CSS Grid packs the result — which is the whole
 * reason there is no collision resolution here, and no answer to "what is at
 * (3, 1)". See `architecture/plans/customisable-dashboards.md`.
 *
 * ## The list is what is *placed*
 *
 * A declared widget whose key is not in the list is not on the dashboard — it is
 * in the tray, waiting to be put back. That is one rule doing two jobs, and the
 * alternative (a `hidden` flag beside the order) would have made "removed" and
 * "never placed" two states that mean the same thing and can disagree.
 *
 * The consequence to hold on to: **an empty list is not the same as no layout.**
 * Nothing stored means the declaration decides, which is what a user who has
 * never touched this dashboard must see. A stored empty list means a user took
 * everything off, and they are entitled to an empty dashboard.
 *
 * ## Everything here arrives from a request
 *
 * The bag was written by the browser, so it is read as hostile: a key that is
 * not declared is dropped rather than conjuring a widget, a size outside the
 * grid is clamped rather than emitting a class that does not exist, and a key
 * listed twice is placed once. None of that is defensive tidiness — a stored
 * `w` of 99 would otherwise reach `getColumnSpanClass()` and silently render as
 * nothing.
 */
final readonly class WidgetLayout
{
    /**
     * @param  array<int, array{key: string, w: int, h: int}>|null  $placements
     *                                                                           Null means nothing was ever stored — the declaration decides.
     */
    private function __construct(private ?array $placements) {}

    /** A dashboard nobody has customised: the declaration is the layout. */
    public static function none(): self
    {
        return new self(null);
    }

    /**
     * Read a layout out of whatever the store handed back.
     *
     * Tolerant by design: the bag is shared with other surfaces and other
     * versions, so anything unrecognisable means "no layout" rather than an
     * error a user cannot act on.
     *
     * @param  array<string, mixed>  $bag
     */
    public static function fromBag(array $bag): self
    {
        $stored = $bag['widgets'] ?? null;

        if (! is_array($stored)) {
            return self::none();
        }

        $placements = [];
        $seen = [];

        foreach ($stored as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $key = $entry['key'] ?? null;

            // A key listed twice is placed once, at its first position: a widget
            // cannot be in two places, and picking the later one would move it
            // for no reason a user could see.
            if (! is_string($key) || $key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $placements[] = [
                'key' => $key,
                'w' => self::clamp($entry['w'] ?? 1, 1, 4),
                'h' => self::clamp($entry['h'] ?? 1, 1, 6),
            ];
        }

        return new self($placements);
    }

    /**
     * @param  array<int, array{key: string, w: int, h: int}>  $placements
     */
    public static function of(array $placements): self
    {
        return self::fromBag(['widgets' => $placements]);
    }

    /** Whether a user has ever laid this dashboard out. */
    public function isDeclared(): bool
    {
        return $this->placements === null;
    }

    /**
     * Order and size the declared widgets according to this layout.
     *
     * Returns the widgets that are *placed*, in the stored order, each carrying
     * the stored size. With nothing stored the declaration is returned
     * untouched — not merely in the same order, but the same objects with the
     * same spans, so a dashboard that was never customised is byte-identical to
     * one from before any of this existed.
     *
     * @param  array<int, Widget>  $widgets  the declared widgets, keys already stamped
     * @return array<int, Widget>
     */
    public function apply(array $widgets): array
    {
        if ($this->placements === null) {
            return $widgets;
        }

        $byKey = [];

        foreach ($widgets as $widget) {
            $key = $widget->getKey();

            if ($key !== null) {
                $byKey[$key] = $widget;
            }
        }

        $placed = [];

        foreach ($this->placements as $placement) {
            $widget = $byKey[$placement['key']] ?? null;

            // A key the declaration no longer has: the widget was renamed or
            // removed by whoever owns the dashboard. Dropping it is the only
            // honest answer — a layout cannot conjure a widget back.
            if ($widget === null) {
                continue;
            }

            $placed[] = $widget->columnSpan($placement['w'])->rowSpan($placement['h']);
        }

        return $placed;
    }

    /**
     * The layout a set of declared widgets already describes.
     *
     * What "start editing" begins from on a dashboard nobody has laid out yet:
     * the declaration, in its own order, at whatever spans it declared. From
     * here on the two are the same kind of thing, which is what lets a drag on
     * an untouched dashboard behave exactly like a drag on a saved one.
     *
     * @param  array<int, Widget>  $widgets  keys already stamped
     */
    public static function fromWidgets(array $widgets): self
    {
        $placements = [];

        foreach ($widgets as $widget) {
            $key = $widget->getKey();

            if ($key === null) {
                continue;
            }

            $span = $widget->getColumnSpan();

            $placements[] = [
                'key' => $key,
                // A span of 'full' or nothing is one column here: this is the
                // number a user will now change with a button, and 'full' is a
                // word rather than a number on that scale.
                'w' => is_int($span) ? self::clamp($span, 1, 4) : 1,
                'h' => self::clamp($widget->getRowSpan() ?? 1, 1, 6),
            ];
        }

        return new self($placements);
    }

    /**
     * Move a widget to a position, closing the gap it leaves behind.
     *
     * The position is where it ends up *after* it has been taken out, which is
     * what a drag reports and the only reading that survives moving an item
     * downwards: taking it out first shifts everything after it up by one, so a
     * position counted before the removal would land one place too far.
     *
     * A key this layout does not have is ignored rather than appended — the
     * browser sent it, and a drag of something that is not here is not a request
     * to add it.
     */
    public function move(string $key, int $position): self
    {
        if ($this->placements === null) {
            return $this;
        }

        $index = $this->indexOf($key);

        if ($index === null) {
            return $this;
        }

        $placements = $this->placements;
        [$moved] = array_splice($placements, $index, 1);

        array_splice($placements, max(0, min(count($placements), $position)), 0, [$moved]);

        return new self($placements);
    }

    /**
     * Put a widget on the dashboard at a position, or move it if it is already
     * there.
     *
     * One method for both because a drop cannot tell them apart and should not
     * have to: the tray and the grid are one drag, and where the tile came from
     * is the layout's business rather than the browser's.
     */
    public function place(string $key, int $position, int $width = 1, int $height = 1): self
    {
        if ($this->indexOf($key) !== null) {
            return $this->move($key, $position);
        }

        $placements = $this->placements ?? [];

        array_splice($placements, max(0, min(count($placements), $position)), 0, [[
            'key' => $key,
            'w' => self::clamp($width, 1, 4),
            'h' => self::clamp($height, 1, 6),
        ]]);

        return new self($placements);
    }

    /**
     * Take a widget off the dashboard.
     *
     * It is not hidden and not remembered as removed — it is simply no longer in
     * the list, which is what puts it back in the tray. That is the one rule
     * doing two jobs again: "not placed" and "available" are the same state, so
     * they cannot disagree.
     */
    public function remove(string $key): self
    {
        $index = $this->indexOf($key);

        if ($this->placements === null || $index === null) {
            return $this;
        }

        $placements = $this->placements;
        array_splice($placements, $index, 1);

        return new self($placements);
    }

    /** Whether a widget is currently on the dashboard. */
    public function has(string $key): bool
    {
        return $this->indexOf($key) !== null;
    }

    /** Resize a widget in place; sizes outside the grid are clamped, as everywhere else. */
    public function resize(string $key, int $width, int $height): self
    {
        if ($this->placements === null) {
            return $this;
        }

        $index = $this->indexOf($key);

        if ($index === null) {
            return $this;
        }

        $placements = $this->placements;
        $placements[$index]['w'] = self::clamp($width, 1, 4);
        $placements[$index]['h'] = self::clamp($height, 1, 6);

        return new self($placements);
    }

    /**
     * The width and height a widget is currently laid out at, or null.
     *
     * @return array{key: string, w: int, h: int}|null
     */
    public function sizeOf(string $key): ?array
    {
        $index = $this->indexOf($key);

        return $index === null ? null : $this->placements[$index];
    }

    private function indexOf(string $key): ?int
    {
        foreach ($this->placements ?? [] as $index => $placement) {
            if ($placement['key'] === $key) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The stored shape, ready for the preference bag.
     *
     * @return array<string, mixed>
     */
    public function toBag(): array
    {
        return ['widgets' => $this->placements ?? []];
    }

    private static function clamp(mixed $value, int $min, int $max): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return $min;
        }

        return max($min, min($max, (int) $value));
    }
}
