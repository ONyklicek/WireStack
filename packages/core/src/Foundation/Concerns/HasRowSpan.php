<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

/**
 * How many grid rows a component occupies.
 *
 * The other half of {@see HasColumnSpan}, and it exists for a reason that only
 * appeared with customisable dashboards: a user resizing a tile wants it taller
 * as often as wider — a chart is worth two rows, a stat card never is.
 *
 * ## Why this is a span and not a height
 *
 * Because a height in pixels does not compose. Two tiles side by side have to
 * agree on where the next row starts, and the only thing that can make them
 * agree is the grid. A span says "two rows of whatever a row is", which stays
 * true when the grid's row height changes, when the viewport narrows, and when
 * the tile beside it is one row tall.
 *
 * The row height itself belongs to the grid, not here — see
 * `wire-core::widgets.widget-grid`, which only sets a row baseline when
 * something on it actually spans rows, so a dashboard that never asked for this
 * renders exactly the markup it always did.
 */
trait HasRowSpan
{
    protected ?int $rowSpan = null;

    /** Set how many grid rows this component spans (1–6; null is one row, sized by content). */
    public function rowSpan(?int $span): static
    {
        $this->rowSpan = $span === null ? null : max(1, min(6, $span));

        return $this;
    }

    public function getRowSpan(): ?int
    {
        return $this->rowSpan;
    }

    /**
     * Canonical grid class for the configured row span.
     *
     * One owner of the span → Tailwind mapping, as `HasColumnSpan` is for
     * columns: the literals have to exist somewhere the scanner can see them,
     * and a `match` is that somewhere. A span of one is the default and needs no
     * class.
     */
    public function getRowSpanClass(string $default = ''): string
    {
        return match ($this->getRowSpan()) {
            2 => 'row-span-2',
            3 => 'row-span-3',
            4 => 'row-span-4',
            5 => 'row-span-5',
            6 => 'row-span-6',
            default => $default,
        };
    }

    /** Whether this component asked to be taller than one row. */
    public function spansRows(): bool
    {
        return $this->getRowSpan() !== null && $this->getRowSpan() > 1;
    }
}
