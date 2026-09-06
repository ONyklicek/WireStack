<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;

/**
 * A fixed width for the column, as a raw CSS length ('100px', '20%').
 *
 * Not normalized on purpose: the value is handed to the header cell's style
 * attribute as written, so any CSS length works and no vocabulary has to be
 * maintained here. Distinct from `HasSize`, which is the column's structural
 * size on the design system's scale rather than a measurement.
 *
 * @phpstan-require-extends Column
 */
trait HasWidth
{
    /** @var string|null Fixed width of the column (e.g., '100px', '20%') */
    protected ?string $width = null;

    /**
     * Set the width of the column.
     */
    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Get the width of the column.
     */
    public function getWidth(): ?string
    {
        return $this->width;
    }
}
