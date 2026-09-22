<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Support;

use NyonCode\WireCore\Foundation\Enums\Breakpoint;

/**
 * Canonical owner of responsive grid-column classes. Accepts a Filament-style
 * per-breakpoint map — e.g. ['default' => 1, 'md' => 2, 'lg' => 3] — and returns
 * the matching literal Tailwind `grid-cols-*` utilities.
 *
 * Tailwind only generates classes it can see as literal text. The runtime line
 * in {@see cols()} interpolates the count, so on its own the scanner would never
 * emit those utilities — {@see scannableClasses()} lists every one as a literal
 * so a consumer scanning the package `src` (see getting-started) generates them.
 * Compatible with Tailwind 3 and 4 (plain `md:grid-cols-2` syntax only).
 */
final class ResponsiveGrid
{
    /** Supported column counts (1–12, matching Tailwind's default grid scale). */
    private const MAX_COLUMNS = 12;

    /**
     * Map columns to a grid-cols class string.
     *
     * An int gives a mobile-first reflow (1 column on phones, up to N from md).
     * An array is a per-breakpoint map: keys are breakpoints ('default'/''/0 =
     * base, then sm|md|lg|xl|2xl), values are column counts (clamped to 1–12);
     * unknown breakpoints are ignored.
     *
     * @param  int|array<string|int, int|string>  $columns
     */
    public static function cols(int|array $columns): string
    {
        if (is_int($columns)) {
            $count = max(1, min($columns, self::MAX_COLUMNS));

            return $count === 1 ? 'grid-cols-1' : 'grid-cols-1 md:grid-cols-'.$count;
        }

        $out = [];

        foreach ($columns as $breakpoint => $count) {
            $prefix = self::prefix((string) $breakpoint);

            if ($prefix === null) {
                continue;
            }

            $count = max(1, min((int) $count, self::MAX_COLUMNS));
            $out[] = $prefix.'grid-cols-'.$count;
        }

        return implode(' ', $out);
    }

    /**
     * How wide one item may be drawn in a grid {@see cols()} built, as
     * `col-span-*` utilities that step with that grid.
     *
     * ## Why a span cannot be one class
     *
     * Because a grid this owner builds is *responsive* and a `col-span-N` is
     * not. `cols(3)` is one column until `md` and three from it; an item asking
     * for three columns is asking for something that exists only above `md`, and
     * a bare `sm:col-span-3` below it does not fail — **CSS Grid invents the
     * missing tracks.** A one-column grid holding a `col-span-3` item silently
     * becomes three columns, and every other item on it is squeezed into a
     * sliver of whatever is left. Nothing errors, nothing logs, and the page is
     * wrong at exactly the widths nobody develops at.
     *
     * So the span is resolved *against the same ladder the grid was built from*:
     * at each breakpoint the item spans what it asked for or what the grid has,
     * whichever is smaller, and a step is emitted only where that number
     * changes. A span of 3 in `cols(['default' => 1, 'md' => 2, 'xl' => 3])` is
     * `md:col-span-2 xl:col-span-3` — two columns while the grid has two, three
     * once it has three, and one on a phone, which is the only honest answer at
     * each width.
     *
     * `'full'` is passed through as `col-span-full`, which is grid-aware by
     * construction: it spans the explicit tracks there are, at every width, and
     * can never conjure one.
     *
     * @param  int|string|null  $span  Column count, `'full'`, or null for one column
     * @param  int|array<string|int, int|string>  $columns  The same argument {@see cols()} was given
     */
    public static function span(int|string|null $span, int|array $columns): string
    {
        if ($span === 'full') {
            return 'col-span-full';
        }

        if (! is_int($span) || $span <= 1) {
            return '';
        }

        $out = [];
        $previous = 1;

        foreach (self::ladder($columns) as $prefix => $count) {
            // What the item can actually have here: the grid cannot give more
            // columns than it has, and asking anyway is what invents a track.
            $effective = max(1, min($span, $count));

            // Only where it changes. A span that is already right at the
            // breakpoint below needs no second class, and the shortest correct
            // answer is the one a reader can check against the grid's own.
            if ($effective === $previous) {
                continue;
            }

            $out[] = $prefix.'col-span-'.$effective;
            $previous = $effective;
        }

        return implode(' ', $out);
    }

    /**
     * The ladder a grid of **form fields** climbs, ready for {@see cols()} and
     * {@see span()}.
     *
     * A field is a label over a control and still reads at half a phone's width,
     * so this ramps early — two columns from `sm`, then one more at each step up
     * to the declared count. It is the ladder the field surfaces (a step, a tab,
     * a fieldset, a record panel, a repeatable entry) have always drawn, written
     * as five copies of one `match` in five Blade views; named here so the span
     * a field is given can be resolved against the same numbers.
     *
     * @return array<string, int>
     */
    public static function fieldColumns(int $columns): array
    {
        return match (max(1, min(4, $columns))) {
            1 => ['default' => 1],
            2 => ['default' => 1, 'sm' => 2],
            3 => ['default' => 1, 'sm' => 2, 'md' => 3],
            default => ['default' => 1, 'sm' => 2, 'md' => 3, 'lg' => 4],
        };
    }

    /**
     * The ladder a grid of **cards** climbs, ready for {@see cols()} and
     * {@see span()}.
     *
     * A card is not a form field. A field is a label over a control and reads
     * fine in half a phone's width, so the field grids ramp at `sm`; a card
     * carries a heading, a figure and often a chart, and three of them at 640px
     * are three columns of nothing. So this ramps later and in two steps — one
     * column on a phone, two from `md`, and the declared count from `xl` — which
     * is the shape the widget grid has always had, named here so that a span
     * drawn in it can be resolved against the same numbers.
     *
     * Capped at four: beyond that a card has no room left, and the cap is what
     * keeps {@see span()}'s answer inside the scannable literals.
     *
     * @return array<string, int>
     */
    public static function cardColumns(int $columns): array
    {
        return match (max(1, min(4, $columns))) {
            1 => ['default' => 1],
            2 => ['default' => 1, 'md' => 2],
            3 => ['default' => 1, 'md' => 2, 'xl' => 3],
            default => ['default' => 1, 'md' => 2, 'xl' => 4],
        };
    }

    /**
     * The column count at each breakpoint, in min-width order.
     *
     * One reading of {@see cols()}'s argument, so a span cannot disagree with
     * the grid it is drawn in: both answer from this. Unknown breakpoints are
     * dropped exactly as `cols()` drops them.
     *
     * @param  int|array<string|int, int|string>  $columns
     * @return array<string, int> Tailwind prefix (`''`, `'md:'`, …) => count
     */
    private static function ladder(int|array $columns): array
    {
        if (is_int($columns)) {
            $count = max(1, min($columns, self::MAX_COLUMNS));

            return $count === 1 ? ['' => 1] : ['' => 1, 'md:' => $count];
        }

        $ladder = [];

        foreach ($columns as $breakpoint => $count) {
            $prefix = self::prefix((string) $breakpoint);

            if ($prefix === null) {
                continue;
            }

            $ladder[$prefix] = max(1, min((int) $count, self::MAX_COLUMNS));
        }

        // In min-width order, whatever order the map was written in: "has this
        // changed since the breakpoint below?" is only a question if there is a
        // below. The base comes first, then the enum's own order.
        $order = ['' => 0];

        foreach (Breakpoint::values() as $index => $value) {
            $order[$value.':'] = $index + 1;
        }

        uksort($ladder, fn (string $a, string $b): int => ($order[$a] ?? 0) <=> ($order[$b] ?? 0));

        return $ladder;
    }

    /**
     * Resolve a breakpoint token to its Tailwind prefix, or null if unknown.
     * '' / 'default' / 0 all map to the base (unprefixed) breakpoint; every named
     * breakpoint delegates to the canonical {@see Breakpoint} enum.
     */
    private static function prefix(string $breakpoint): ?string
    {
        if ($breakpoint === '' || $breakpoint === 'default' || $breakpoint === '0') {
            return '';
        }

        return Breakpoint::tryFromToken($breakpoint)?->prefix();
    }

    /**
     * Every utility this owner can emit — {@see cols()}'s grid-cols and
     * {@see span()}'s col-span — written out as LITERAL strings so Tailwind's
     * text scanner generates them (an interpolated `$prefix.'grid-cols-'.$count`
     * would be invisible to it). Never called at runtime — its only job is to
     * exist as scannable text in this file.
     *
     * @return list<string>
     */
    public static function scannableClasses(): array
    {
        return [
            'col-span-full',
            'col-span-1', 'col-span-2', 'col-span-3', 'col-span-4', 'col-span-5', 'col-span-6', 'col-span-7', 'col-span-8', 'col-span-9', 'col-span-10', 'col-span-11', 'col-span-12',
            'sm:col-span-1', 'sm:col-span-2', 'sm:col-span-3', 'sm:col-span-4', 'sm:col-span-5', 'sm:col-span-6', 'sm:col-span-7', 'sm:col-span-8', 'sm:col-span-9', 'sm:col-span-10', 'sm:col-span-11', 'sm:col-span-12',
            'md:col-span-1', 'md:col-span-2', 'md:col-span-3', 'md:col-span-4', 'md:col-span-5', 'md:col-span-6', 'md:col-span-7', 'md:col-span-8', 'md:col-span-9', 'md:col-span-10', 'md:col-span-11', 'md:col-span-12',
            'lg:col-span-1', 'lg:col-span-2', 'lg:col-span-3', 'lg:col-span-4', 'lg:col-span-5', 'lg:col-span-6', 'lg:col-span-7', 'lg:col-span-8', 'lg:col-span-9', 'lg:col-span-10', 'lg:col-span-11', 'lg:col-span-12',
            'xl:col-span-1', 'xl:col-span-2', 'xl:col-span-3', 'xl:col-span-4', 'xl:col-span-5', 'xl:col-span-6', 'xl:col-span-7', 'xl:col-span-8', 'xl:col-span-9', 'xl:col-span-10', 'xl:col-span-11', 'xl:col-span-12',
            '2xl:col-span-1', '2xl:col-span-2', '2xl:col-span-3', '2xl:col-span-4', '2xl:col-span-5', '2xl:col-span-6', '2xl:col-span-7', '2xl:col-span-8', '2xl:col-span-9', '2xl:col-span-10', '2xl:col-span-11', '2xl:col-span-12',
            'grid-cols-1', 'grid-cols-2', 'grid-cols-3', 'grid-cols-4', 'grid-cols-5', 'grid-cols-6', 'grid-cols-7', 'grid-cols-8', 'grid-cols-9', 'grid-cols-10', 'grid-cols-11', 'grid-cols-12',
            'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-4', 'sm:grid-cols-5', 'sm:grid-cols-6', 'sm:grid-cols-7', 'sm:grid-cols-8', 'sm:grid-cols-9', 'sm:grid-cols-10', 'sm:grid-cols-11', 'sm:grid-cols-12',
            'md:grid-cols-1', 'md:grid-cols-2', 'md:grid-cols-3', 'md:grid-cols-4', 'md:grid-cols-5', 'md:grid-cols-6', 'md:grid-cols-7', 'md:grid-cols-8', 'md:grid-cols-9', 'md:grid-cols-10', 'md:grid-cols-11', 'md:grid-cols-12',
            'lg:grid-cols-1', 'lg:grid-cols-2', 'lg:grid-cols-3', 'lg:grid-cols-4', 'lg:grid-cols-5', 'lg:grid-cols-6', 'lg:grid-cols-7', 'lg:grid-cols-8', 'lg:grid-cols-9', 'lg:grid-cols-10', 'lg:grid-cols-11', 'lg:grid-cols-12',
            'xl:grid-cols-1', 'xl:grid-cols-2', 'xl:grid-cols-3', 'xl:grid-cols-4', 'xl:grid-cols-5', 'xl:grid-cols-6', 'xl:grid-cols-7', 'xl:grid-cols-8', 'xl:grid-cols-9', 'xl:grid-cols-10', 'xl:grid-cols-11', 'xl:grid-cols-12',
            '2xl:grid-cols-1', '2xl:grid-cols-2', '2xl:grid-cols-3', '2xl:grid-cols-4', '2xl:grid-cols-5', '2xl:grid-cols-6', '2xl:grid-cols-7', '2xl:grid-cols-8', '2xl:grid-cols-9', '2xl:grid-cols-10', '2xl:grid-cols-11', '2xl:grid-cols-12',
        ];
    }
}
