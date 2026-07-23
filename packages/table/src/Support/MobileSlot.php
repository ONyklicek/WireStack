<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Support;

/**
 * Where a column lands on a stacked mobile card.
 *
 * A phone card is read, not scanned across: it needs a hierarchy (what is this,
 * whose is it, how much) rather than the column order the desktop grid happens
 * to use. These are the named places that hierarchy is made of; {@see MobileCard}
 * assigns every column to one, from an explicit choice or by derivation.
 */
enum MobileSlot: string
{
    /** The identifier the record is recognised by — one per card. */
    case Title = 'title';

    /** The supporting line under the title (a customer, an owner). */
    case Subtitle = 'subtitle';

    /** The figure the list is read for, set right on the title line. */
    case Metric = 'metric';

    /** Status and at most a couple of qualifiers. */
    case Meta = 'meta';

    /** Everything else: the label/value grid below. */
    case Detail = 'detail';

    /**
     * Resolve a slot from a name, tolerating the string form so the fluent API
     * can accept both.
     */
    public static function resolve(self|string $slot): self
    {
        return $slot instanceof self ? $slot : self::from($slot);
    }

    /**
     * Slots that hold exactly one column, so a later assignment replaces an
     * earlier one instead of stacking.
     */
    public function isSingular(): bool
    {
        return $this !== self::Meta && $this !== self::Detail;
    }
}
