<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireTable\Columns\MoneyColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Support\MobileCard;

/*
 * A money column, and the formatting its base already owned.
 *
 * `FormatsState::money()` is the canonical formatter and is shared with infolist
 * entries, so nothing here re-implements it — the fluent surface is widened and
 * the column changes its defaults. What the defaults buy is the point: alignment
 * is how `MobileCard` picks the figure a phone shows on a stacked card, so a
 * money column becoming the card's metric is a consequence of getting the
 * desktop right, not a second declaration.
 *
 * The formatter's precision rule was documented backwards before this. A test
 * named "formats money values correctly for CZK (0 decimals)" asserted only
 * `toContain('CZK')`, and CZK renders two decimals — only the literal symbol
 * `Kč` renders whole crowns. It is pinned outright below.
 */

class MoneyRow extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

function moneyRecord(): MoneyRow
{
    return new MoneyRow(['total' => 1234.5]);
}

// ─── The formatter (canonical, shared with infolist entries) ─────────────────

it('keys the default precision on how the currency is spelled', function () {
    $record = moneyRecord();

    // Inherited and deliberately kept: the ISO code renders hellers, the symbol
    // renders whole crowns. Same currency, two spellings, two precisions.
    expect(TextColumn::make('total')->money('CZK')->formatValue(1234.5, $record))->toBe('1 234,50 CZK')
        ->and(TextColumn::make('total')->money('Kč')->formatValue(1234.5, $record))->toBe('1 235 Kč')
        ->and(TextColumn::make('total')->money('CZK')->getMoneyDecimals())->toBe(2)
        ->and(TextColumn::make('total')->money('Kč')->getMoneyDecimals())->toBe(0);
});

it('lets the precision and the separators be stated outright', function () {
    $record = moneyRecord();

    expect(TextColumn::make('total')->money('EUR', 2, '.', ',')->formatValue(1234.5, $record))
        ->toBe('1,234.50 EUR')
        // A stated precision beats the per-currency default, in both directions.
        ->and(TextColumn::make('total')->money('Kč', 2)->formatValue(1234.5, $record))->toBe('1 234,50 Kč')
        ->and(TextColumn::make('total')->money('CZK', 0)->formatValue(1234.5, $record))->toBe('1 235 CZK');
});

it('does not overwrite a stated setting with an omitted argument', function () {
    // currency() calls money() with one argument; the separators set earlier
    // have to survive it, or the sugar would quietly reset the format.
    $column = TextColumn::make('total')->money('EUR', 2, '.', ',');

    expect($column->money('USD')->formatValue(1234.5, moneyRecord()))->toBe('1,234.50 USD');
});

it('renders a bare amount when there is no currency to name', function () {
    // A column headed "Total (EUR)" wants the figure alone. This used to append
    // the separator regardless and hand back a trailing space.
    expect(TextColumn::make('total')->money(null)->formatValue(1234.5, moneyRecord()))->toBe('1 234,50')
        ->and(MoneyColumn::make('total')->withoutCurrency()->formatValue(1234.5, moneyRecord()))->toBe('1 234,50');
});

it('puts the currency before the amount when the convention asks', function () {
    expect(TextColumn::make('total')->money('CZK')->usesCurrencyBefore())->toBeFalse()
        ->and(TextColumn::make('total')->money('$')->currencyBefore()->usesCurrencyBefore())->toBeTrue()
        ->and(TextColumn::make('total')->money('$', 2, '.', ',')->currencyBefore()->formatValue(1234.5, moneyRecord()))
        ->toBe('$ 1,234.50')
        ->and(TextColumn::make('total')->money('CZK')->currencyBefore(false)->formatValue(1234.5, moneyRecord()))
        ->toBe('1 234,50 CZK');
});

// ─── What the column changes ─────────────────────────────────────────────────

it('reads as a figure by default: right-aligned, tabular, unwrapped', function () {
    $column = MoneyColumn::make('total');

    expect($column->isMoney())->toBeTrue()
        ->and($column->getAlignment())->toBe('right')
        ->and($column->getTextClasses())->toContain('tabular-nums')
        ->toContain('whitespace-nowrap');
});

it('keeps every one of those overridable', function () {
    // Defaults, not rules. A money column in a narrow layout may still be told
    // to sit left, and the text classes it inherits are untouched.
    $column = MoneyColumn::make('total')->alignment('left')->textSize('lg');

    expect($column->getAlignment())->toBe('left')
        ->and($column->getTextClasses())->toContain('text-lg')
        ->toContain('tabular-nums');
});

it('is the same formatter the base exposes', function () {
    // Nothing is re-implemented here: the preset and the fluent call agree.
    expect(MoneyColumn::make('total')->currency('CZK')->formatValue(1234.5, moneyRecord()))
        ->toBe(TextColumn::make('total')->money('CZK')->formatValue(1234.5, moneyRecord()));
});

// ─── …and what that buys on a phone ──────────────────────────────────────────

it('becomes the metric a stacked card is read for', function () {
    // MobileCard derives the metric from the last right-aligned column. A money
    // column is one without being told, so the amount lands on the card's
    // headline slot instead of in the label/value grid at the bottom.
    $card = MobileCard::resolve([
        TextColumn::make('number'),
        TextColumn::make('customer'),
        MoneyColumn::make('total'),
    ]);

    expect($card->metric()?->getName())->toBe('total')
        ->and($card->details())->toBe([]);
});

it('leaves the slot alone when the column is told to sit left', function () {
    $card = MobileCard::resolve([
        TextColumn::make('number'),
        TextColumn::make('customer'),
        MoneyColumn::make('total')->alignment('left'),
    ]);

    // No right-aligned column left to be the metric, so the amount falls back to
    // the detail grid. The derivation follows the alignment, not the class — a
    // MoneyColumn told to sit left is not a metric.
    expect($card->metric())->toBeNull()
        ->and(array_map(fn ($c) => $c->getName(), $card->details()))->toBe(['total']);
});

it('yields the metric slot to an explicit declaration', function () {
    $card = MobileCard::resolve(
        [TextColumn::make('number'), MoneyColumn::make('total'), TextColumn::make('due')->alignRight()],
        fn ($config) => $config->metric('due'),
    );

    expect($card->metric()?->getName())->toBe('due')
        ->and($card->title()?->getName())->toBe('number');
});

it('renders the figure classes into the cell', function () {
    // The classes have to reach the markup, not merely the getter: the cell view
    // is the only place the alignment and the tabular digits are visible.
    $html = MoneyColumn::make('total')->renderCell(moneyRecord());

    expect($html)->toContain('tabular-nums')
        ->toContain('1 234,50 CZK');
});
