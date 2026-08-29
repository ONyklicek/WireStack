<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use NyonCode\WireCore\Foundation\Enums\Alignment;

/**
 * An amount of money, presented the way a figure wants to be read.
 *
 * The formatting is not this class's — `FormatsState::money()` owns it and
 * `TextColumn` already exposes it, so `TextColumn::make('total')->money()` keeps
 * working and produces the same string. What a subclass can do that a fluent call
 * on the base cannot is change the column's **defaults**, and for a money column
 * three of them are wrong out of the box:
 *
 * 1. **It aligns right.** Figures are compared down a column, and that only works
 *    against a fixed right edge. In this framework the alignment carries a second
 *    meaning: `Support\MobileCard` derives the stacked card's metric slot from the
 *    last right-aligned column, so a money column becomes the figure a phone shows
 *    on the card — the one thing the list is read for — without being told to.
 * 2. **Its digits are tabular.** Proportional digits make 1 and 7 different widths,
 *    so the decimal points wander even under a right edge. `tabular-nums` is the
 *    same vocabulary the summary footers and the mobile card already use.
 * 3. **It does not wrap.** An amount broken across two lines stops being one number.
 *
 * Every one of those is a default, not a rule: `->alignment('left')` and the text
 * classes stay exactly as overridable as on any other column.
 *
 * ```php
 * MoneyColumn::make('total')->currency('EUR')->currencyBefore()->summarizeSum('Total')
 * ```
 */
class MoneyColumn extends TextColumn
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        // A money column is money; saying ->money() again is allowed but idle.
        $this->money();
        $this->alignment(Alignment::Right);
    }

    /**
     * The currency this column renders in — the same argument `money()` takes,
     * named for what it is on a column that is already money.
     */
    public function currency(?string $currency): static
    {
        return $this->money($currency);
    }

    /**
     * Render the amount without any currency at all — a bare formatted figure,
     * for the common case of a column headed "Total (EUR)".
     */
    public function withoutCurrency(): static
    {
        return $this->money(null);
    }

    /**
     * Figures read down the column, so the digits have to be the same width and
     * the amount has to stay on one line. Appended to the canonical text classes
     * rather than replacing them, so size, weight, colour and font family are
     * untouched.
     */
    public function getTextClasses(): string
    {
        return trim(parent::getTextClasses().' tabular-nums whitespace-nowrap');
    }
}
