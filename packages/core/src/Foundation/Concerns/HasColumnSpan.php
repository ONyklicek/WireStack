<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use NyonCode\WireCore\Foundation\Support\ResponsiveGrid;

/**
 * Column span for grid layout.
 */
trait HasColumnSpan
{
    protected int|string|null $columnSpan = null;

    /**
     * The grid this component will be drawn in, as the layout declared it.
     *
     * Null until a grid says otherwise, and that is the whole of the coupling: a
     * component still knows nothing about where it is, it is *told* on the way
     * in. See {@see inGridOf()} for why it has to be told at all.
     *
     * @var int|array<string|int, int|string>|null
     */
    protected int|array|null $gridColumns = null;

    /** Set how many grid columns this component spans (an int, or a per-breakpoint string). */
    public function columnSpan(int|string $span): static
    {
        $this->columnSpan = $span;

        return $this;
    }

    /** Span the full width of the grid row. */
    public function columnSpanFull(): static
    {
        $this->columnSpan = 'full';

        return $this;
    }

    public function getColumnSpan(): int|string|null
    {
        return $this->columnSpan;
    }

    /**
     * Tell this component which grid it is about to be drawn in.
     *
     * Called by the layout that owns the grid, immediately before rendering the
     * child — the only moment both halves are known, since a component is
     * declared without knowing where it will be put and a grid is declared
     * without knowing what will be put in it.
     *
     * It has to be known because **a span is only meaningful against a column
     * count.** A grid is responsive: `columns(3)` is one column on a phone and
     * three on a desktop, so "span 3" is a different class at each width. Worse,
     * a span wider than the grid does not clip — CSS Grid answers by *adding*
     * the missing track, so one over-wide child silently re-flows the whole
     * layout and squeezes its siblings into the remainder. Given the grid, that
     * cannot be emitted; without it, it is the default.
     *
     * @param  int|array<string|int, int|string>  $columns  The same value the layout passed to `ResponsiveGrid::cols()`
     */
    public function inGridOf(int|array $columns): static
    {
        $this->gridColumns = $columns;

        return $this;
    }

    /**
     * Canonical responsive grid class for the configured column span.
     *
     * Single owner of the span → Tailwind mapping so every grid surface (form
     * fields, infolist entries, layout components) stays in sync instead of
     * re-encoding the same `match` in each Blade view.
     *
     * Resolved against the grid this component was told it is in
     * ({@see inGridOf()}), through the same owner that built that grid — so the
     * span steps with the columns rather than stating one number that is only
     * true at some widths. A component nobody told assumes a grid exactly as
     * wide as the span it asked for, which is the narrowest assumption that
     * still honours the declaration.
     *
     * `full` spans every column at every width and is grid-aware by
     * construction. Anything else falls back to `$default` — block-level
     * surfaces (key/value, repeatable) pass `col-span-full` so an unset span
     * still fills the row.
     */
    public function getColumnSpanClass(string $default = ''): string
    {
        $span = $this->getColumnSpan();

        if ($span === null || ($span !== 'full' && ! is_int($span))) {
            return $default;
        }

        return ResponsiveGrid::span($span, $this->gridColumns ?? (is_int($span) ? $span : 1)) ?: $default;
    }
}
