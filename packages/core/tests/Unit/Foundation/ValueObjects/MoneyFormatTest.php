<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\ValueObjects\MoneyFormat;

/**
 * The currency vocabulary, owned once for both directions. A display surface
 * only ever calls format(); MoneyInput reads a typed amount back with parse(),
 * which is why the two live together and are tested against each other.
 */
it('writes the amount with the currency on the side its convention puts it', function () {
    expect((new MoneyFormat('Kč'))->format(1234))->toBe('1 234 Kč')
        ->and((new MoneyFormat('$', currencyBefore: true))->format(1234.5))->toBe('$ 1 234,50');
});

it('keeps the two decimals of an ISO code and drops them for the colloquial symbol', function () {
    // Keyed on the spelling, not on the currency — 'Kč' is written in whole
    // crowns, 'CZK' in hellers.
    expect((new MoneyFormat('CZK'))->getDecimals())->toBe(2)
        ->and((new MoneyFormat('Kč'))->getDecimals())->toBe(0)
        ->and(MoneyFormat::defaultDecimals('EUR'))->toBe(2);
});

it('lets a stated precision win over the convention', function () {
    expect((new MoneyFormat('Kč', decimals: 2))->format(1234.567))->toBe('1 234,57 Kč');
});

it('writes a bare figure when there is no currency', function () {
    expect((new MoneyFormat(null))->format(1234.5))->toBe('1 234,50')
        ->and((new MoneyFormat(''))->format(1234.5))->toBe('1 234,50');
});

it('writes the amount alone when the currency is rendered elsewhere', function () {
    // MoneyInput puts the currency in the field's affix, so the input itself
    // shows the figure only.
    expect((new MoneyFormat('EUR'))->amount(1234.5))->toBe('1 234,50');
});

it('reads back what it wrote', function (string $currency, float $amount) {
    $format = new MoneyFormat($currency);

    expect($format->parse($format->format($amount)))->toBe($amount);
})->with([
    ['CZK', 1234.5],
    ['Kč', 1234.0],
    ['EUR', 0.05],
    ['USD', -99.99],
]);

it('discards grouping written in either convention', function () {
    $czech = new MoneyFormat('CZK');

    expect($czech->parse('1 234,50'))->toBe(1234.5)
        ->and($czech->parse('1.234,50'))->toBe(1234.5)
        ->and($czech->parse("1\u{00A0}234,50"))->toBe(1234.5)
        ->and((new MoneyFormat('USD', decimalSeparator: '.', thousandsSeparator: ','))->parse('1,234.50'))
        ->toBe(1234.5);
});

it('reads a lone dot as the decimal point in a comma format', function () {
    // What the numeric keypad emits. Discarding it would turn 1234.50 into
    // 123450 — an amount a hundred times too large, silently.
    expect((new MoneyFormat('CZK'))->parse('1234.50'))->toBe(1234.5);
});

it('treats repeated separators as grouping and keeps the last as the point', function () {
    expect((new MoneyFormat('CZK'))->parse('1.234.567'))->toBe(1234567.0)
        ->and((new MoneyFormat('USD', decimalSeparator: '.', thousandsSeparator: ','))->parse('1.234.50'))
        ->toBe(1234.5);
});

it('answers null for an input holding no number', function () {
    $format = new MoneyFormat('CZK');

    expect($format->parse(null))->toBeNull()
        ->and($format->parse(''))->toBeNull()
        ->and($format->parse('Kč'))->toBeNull()
        ->and($format->parse(','))->toBeNull();
});

it('passes a value that is already a number straight through', function () {
    $format = new MoneyFormat('CZK');

    expect($format->parse(1234))->toBe(1234.0)
        ->and($format->parse(12.5))->toBe(12.5);
});

it('converts to and from whole minor units at its own precision', function () {
    $euro = new MoneyFormat('EUR');

    expect($euro->toMinorUnits(12.34))->toBe(1234)
        // Rounded, not truncated: 12.345 is 1235 hellers, not 1234.
        ->and($euro->toMinorUnits(12.345))->toBe(1235)
        ->and($euro->fromMinorUnits(1234))->toBe(12.34)
        // A whole-crown format has no minor unit to divide by.
        ->and((new MoneyFormat('Kč'))->toMinorUnits(1234))->toBe(1234);
});
