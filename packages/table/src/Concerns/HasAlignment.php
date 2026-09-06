<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireTable\Columns\Column;

/**
 * Horizontal text alignment of the column's cells and header.
 *
 * Configuration only: the vocabulary and its literal Tailwind class belong to the
 * canonical {@see Alignment} enum, and this trait does no more than hold a token
 * and hand it over. `left` is the default rather than a stored value, so an
 * unaligned column and an explicitly left-aligned one render the same class.
 *
 * **`alignCenter()` is overridden by `Columns\SplitColumn` with a different
 * meaning** — there it aligns the two halves of a split cell on the cross axis
 * and takes a `bool`. The subclass declaration wins over this trait exactly as it
 * won over the method on `Column` before, so nothing changes; but the name means
 * two things in one hierarchy, which is worth knowing before adding a third.
 *
 * @phpstan-require-extends Column
 */
trait HasAlignment
{
    /** @var string|null Text alignment within the column ('left', 'center', 'right') */
    protected ?string $alignment = null;

    /**
     * Align the column text to the left.
     */
    public function alignLeft(): static
    {
        return $this->alignment(Alignment::Left);
    }

    /**
     * Set the text alignment of the column.
     */
    public function alignment(string|Alignment $alignment): static
    {
        $this->alignment = $alignment instanceof Alignment ? $alignment->value : $alignment;

        return $this;
    }

    /**
     * Center-align the column text.
     */
    public function alignCenter(): static
    {
        return $this->alignment(Alignment::Center);
    }

    /**
     * Align the column text to the right.
     */
    public function alignRight(): static
    {
        return $this->alignment(Alignment::Right);
    }

    /**
     * Get the current text alignment of the column.
     */
    public function getAlignment(): string
    {
        return $this->alignment ?? Alignment::Left->value;
    }

    /**
     * Canonical literal Tailwind text-alignment class for this column, so the
     * view consumes a scannable utility instead of interpolating `text-{$align}`.
     */
    public function getAlignmentClass(): string
    {
        return Alignment::resolve($this->getAlignment())->textClass();
    }
}
