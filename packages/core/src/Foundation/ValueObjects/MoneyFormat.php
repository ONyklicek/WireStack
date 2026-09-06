<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\ValueObjects;

/**
 * How an amount is written down — the one owner of the currency vocabulary
 * (precision, separators, which side the currency reads on) shared by the
 * display surfaces (`Foundation\Concerns\FormatsState::money()`, and through it
 * every `TextColumn`, `MoneyColumn` and `TextEntry`) and the input surface
 * (`WireForms\Components\MoneyInput`).
 *
 * Display only ever writes a number out. An input has to read one back, so the
 * two directions travel together here: `parse()` is `format()`'s inverse over
 * the same separators, and a surface that held its own copy of either would
 * drift from the other the first time a separator changed.
 *
 * The object is immutable and cheap: a surface builds one from its own
 * configuration whenever it needs to write or read an amount.
 */
final class MoneyFormat
{
    public function __construct(
        public readonly ?string $currency = 'CZK',
        public readonly ?int $decimals = null,
        public readonly string $decimalSeparator = ',',
        public readonly string $thousandsSeparator = ' ',
        public readonly bool $currencyBefore = false,
    ) {}

    /**
     * The precision an amount is written with — the stated one, or the
     * currency's own convention.
     */
    public function getDecimals(): int
    {
        return $this->decimals ?? self::defaultDecimals($this->currency);
    }

    /**
     * Precision by convention, keyed on how the currency is *spelled* rather
     * than on what it is: 'Kč' is the colloquial Czech form and is written
     * without hellers, while the ISO code 'CZK' keeps the two decimals every
     * other currency here does.
     */
    public static function defaultDecimals(?string $currency): int
    {
        return $currency === 'Kč' ? 0 : 2;
    }

    /** The grouped, rounded amount on its own — no currency. */
    public function amount(int|float|string $value): string
    {
        return number_format(
            (float) $value,
            $this->getDecimals(),
            $this->decimalSeparator,
            $this->thousandsSeparator,
        );
    }

    /**
     * The amount with the currency on the side its convention puts it.
     *
     * A null or empty currency means "a formatted amount, no currency" and
     * returns the bare figure — without it this used to append the separator
     * anyway and leave a trailing space behind the number.
     */
    public function format(int|float|string $value): string
    {
        $amount = $this->amount($value);

        if ((string) $this->currency === '') {
            return $amount;
        }

        return $this->currencyBefore
            ? $this->currency.' '.$amount
            : $amount.' '.$this->currency;
    }

    /**
     * Read a written amount back — the inverse of {@see format()}.
     *
     * Everything that is not a digit or the decimal separator is discarded, so
     * the currency, the grouping separators and a non-breaking space of any
     * width all fall away without having to be named. Returns null when what is
     * left holds no number at all — the answer a caller with an empty input
     * wants. A minus anywhere in the input makes the amount negative.
     *
     * One deliberate leniency: in a comma-decimal format a lone `.` with no
     * comma anywhere is read as the decimal point, because that is what a
     * numeric keypad emits and discarding it would turn `1234.50` into 123450.
     */
    public function parse(int|float|string|null $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $negative = str_contains($value, '-');

        $separator = $this->decimalSeparator;

        if ($separator === ',' && ! str_contains($value, ',') && substr_count($value, '.') === 1) {
            $separator = '.';
        }

        // Grouping is whatever is left over: dropping every character but the
        // digits and this format's decimal separator removes it without the
        // parser having to know which of the two conventions wrote the number.
        $digits = preg_replace('/[^0-9'.preg_quote($separator, '/').']/u', '', $value) ?? '';

        // Repeated separators cannot all be decimal points — the last one is,
        // and the earlier ones were grouping written in the same character.
        if (substr_count($digits, $separator) > 1) {
            $last = (int) strrpos($digits, $separator);
            $digits = str_replace($separator, '', substr($digits, 0, $last)).substr($digits, $last);
        }

        $digits = str_replace($separator, '.', $digits);

        if ($digits === '' || $digits === '.') {
            return null;
        }

        return (float) ($negative ? '-'.$digits : $digits);
    }

    /**
     * The amount as whole minor units (hellers, cents) — what an integer money
     * column stores. Rounded at this format's precision, so 12.345 at two
     * decimals is 1235 rather than a truncated 1234.
     */
    public function toMinorUnits(int|float|string $value): int
    {
        return (int) round(((float) $value) * $this->minorUnitFactor());
    }

    /** Whole minor units back to the major amount `format()` writes. */
    public function fromMinorUnits(int|float|string $value): float
    {
        return ((float) $value) / $this->minorUnitFactor();
    }

    /** How many minor units go into one major unit at this precision. */
    private function minorUnitFactor(): int
    {
        return 10 ** $this->getDecimals();
    }
}
