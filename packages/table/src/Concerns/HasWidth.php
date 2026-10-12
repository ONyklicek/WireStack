<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;

/**
 * CSS width constraints for a column.
 *
 * Values are passed to the header cell unchanged. This accepts CSS lengths,
 * percentages, `auto`, `min-content`, `max-content`, and other valid CSS width
 * values without maintaining a package-specific vocabulary. It is distinct
 * from `HasSize`, which sets the column's structural design-system size.
 *
 * @phpstan-require-extends Column
 */
trait HasWidth
{
    /** @var string|null Preferred CSS width of the column. */
    protected ?string $width = null;

    /** @var string|null Minimum CSS width of the column. */
    protected ?string $minWidth = null;

    /** @var string|null Maximum CSS width of the column. */
    protected ?string $maxWidth = null;

    /** Set the preferred CSS width of the column. */
    public function width(string $width): static
    {
        $this->width = $width;

        return $this;
    }

    /** Set the minimum CSS width of the column. */
    public function minWidth(string $width): static
    {
        $this->minWidth = $width;

        return $this;
    }

    /** Set the maximum CSS width of the column. */
    public function maxWidth(string $width): static
    {
        $this->maxWidth = $width;

        return $this;
    }

    /** Get the preferred CSS width of the column. */
    public function getWidth(): ?string
    {
        return $this->width;
    }

    /** Get the minimum CSS width of the column. */
    public function getMinWidth(): ?string
    {
        return $this->minWidth;
    }

    /** Get the maximum CSS width of the column. */
    public function getMaxWidth(): ?string
    {
        return $this->maxWidth;
    }

    /**
     * Get the CSS declarations applied to the column header.
     *
     * @docs-ignore Internal render metadata; document the fluent width API instead.
     */
    public function getWidthStyles(): ?string
    {
        $styles = [];

        foreach ([
            'width' => $this->width,
            'min-width' => $this->minWidth,
            'max-width' => $this->maxWidth,
        ] as $property => $value) {
            if ($value !== null && $value !== '') {
                $styles[] = $property.': '.$value;
            }
        }

        return $styles === [] ? null : implode('; ', $styles);
    }
}
