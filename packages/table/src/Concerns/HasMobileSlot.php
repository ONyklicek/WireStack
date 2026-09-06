<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Support\MobileSlot;

/**
 * Where this column lands in the stacked card a table draws instead of a row on
 * a phone.
 *
 * Pure declaration, and only ever a *narrowing* one: without it
 * {@see \NyonCode\WireTable\Support\MobileCard} derives a slot from column order
 * and alignment, and every named method here says "not that one, this". The card
 * side of the same feature is the host concern
 * {@see StacksOnMobile}.
 *
 * Distinct from {@see HasResponsive}, which decides whether the column is drawn
 * at a given width at all. A column can be laid out as the card's title and
 * still be hidden below `md`.
 *
 * @phpstan-require-extends Column
 */
trait HasMobileSlot
{
    /** Explicit stacked-card slot; null lets MobileCard derive one. */
    protected ?MobileSlot $mobileSlot = null;

    /**
     * Place this column in a named slot of the stacked mobile card, instead of
     * letting {@see MobileCard} derive one from column order and alignment.
     */
    public function mobileSlot(MobileSlot|string $slot): static
    {
        $this->mobileSlot = MobileSlot::resolve($slot);

        return $this;
    }

    /**
     * The identifier the card is recognised by.
     */
    public function mobileTitle(): static
    {
        return $this->mobileSlot(MobileSlot::Title);
    }

    /**
     * The supporting line under the title.
     */
    public function mobileSubtitle(): static
    {
        return $this->mobileSlot(MobileSlot::Subtitle);
    }

    /**
     * The figure the list is read for — set right on the title line.
     */
    public function mobileMetric(): static
    {
        return $this->mobileSlot(MobileSlot::Metric);
    }

    /**
     * A status or qualifier, shown beside the title block rather than as a
     * label/value pair.
     */
    public function mobileMeta(): static
    {
        return $this->mobileSlot(MobileSlot::Meta);
    }

    /**
     * Keep this column in the label/value grid, whatever derivation would pick.
     */
    public function mobileDetail(): static
    {
        return $this->mobileSlot(MobileSlot::Detail);
    }

    public function getMobileSlot(): ?MobileSlot
    {
        return $this->mobileSlot;
    }
}
