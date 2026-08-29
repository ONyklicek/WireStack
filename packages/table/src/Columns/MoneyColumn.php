<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use NyonCode\WireTable\Concerns\RendersAsFigure;

/**
 * An amount of money, presented the way a figure wants to be read.
 *
 * The formatting is not this class's — `FormatsState::money()` owns it and
 * `TextColumn` already exposes it, so `TextColumn::make('total')->money()` keeps
 * working and produces the same string. What a subclass can do that a fluent call
 * on the base cannot is change the column's **defaults**, and for a money column
 * three of them are wrong out of the box:
 *
 * Those defaults are {@see RendersAsFigure} — right-aligned, tabular digits, no
 * wrapping — shared with {@see MetricColumn} so the two cannot drift apart. The
 * alignment is the one with reach: `Support\MobileCard` derives the stacked
 * card's metric slot from the last right-aligned column, so a money column
 * becomes the figure a phone shows on the card without being told to.
 *
 * What is left to this class is the currency vocabulary.
 *
 * ```php
 * MoneyColumn::make('total')->currency('EUR')->currencyBefore()->summarizeSum('Total')
 * ```
 */
class MoneyColumn extends TextColumn
{
    use RendersAsFigure;

    public function __construct(string $name)
    {
        parent::__construct($name);

        // A money column is money; saying ->money() again is allowed but idle.
        $this->money();
        $this->readAsFigure();
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
}
