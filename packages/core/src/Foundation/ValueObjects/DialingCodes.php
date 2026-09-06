<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\ValueObjects;

/**
 * The dialling codes the phone surfaces offer: the one place a written number is
 * matched back to a country, and the one place it is written down again.
 *
 * It lives in core rather than beside the field because three surfaces read it —
 * the `PhoneInput` field, the table's `PhoneColumn`, and the rule that validates
 * either — and a table of prefixes copied per surface is a table that disagrees
 * with itself the first time a country is added.
 *
 * The table is deliberately a curated list rather than a copy of the ITU
 * register: it carries the countries an application in this part of the world
 * actually offers, each with the digit range its national numbers have, which is
 * what makes a typed number checkable at all. An unlisted country is not an
 * error — the phone rule falls back to the E.164 bounds for anything this
 * table does not know.
 *
 * A prefix is not unique (`+1` is the whole North American plan), so
 * {@see match()} answers with the first country holding it. The choice is
 * cosmetic — it decides which flag the select shows, never whether the number
 * is valid, because the countries sharing a prefix share its digit range too.
 */
final class DialingCodes
{
    /**
     * ISO 3166-1 alpha-2 => [dialling code, min national digits, max].
     *
     * @var array<string, array{string, int, int}>
     */
    private const CODES = [
        'CZ' => ['+420', 9, 9],
        'SK' => ['+421', 9, 9],
        'PL' => ['+48', 9, 9],
        'DE' => ['+49', 6, 11],
        'AT' => ['+43', 4, 13],
        'HU' => ['+36', 8, 9],
        'CH' => ['+41', 9, 9],
        'GB' => ['+44', 9, 10],
        'IE' => ['+353', 7, 9],
        'FR' => ['+33', 9, 9],
        'BE' => ['+32', 8, 9],
        'NL' => ['+31', 9, 9],
        'LU' => ['+352', 9, 9],
        'ES' => ['+34', 9, 9],
        'PT' => ['+351', 9, 9],
        'IT' => ['+39', 6, 11],
        'DK' => ['+45', 8, 8],
        'SE' => ['+46', 7, 13],
        'NO' => ['+47', 8, 8],
        'FI' => ['+358', 5, 12],
        'EE' => ['+372', 7, 8],
        'LV' => ['+371', 8, 8],
        'LT' => ['+370', 8, 8],
        'SI' => ['+386', 8, 8],
        'HR' => ['+385', 8, 9],
        'RS' => ['+381', 8, 9],
        'BA' => ['+387', 8, 8],
        'RO' => ['+40', 9, 9],
        'BG' => ['+359', 8, 9],
        'GR' => ['+30', 10, 10],
        'UA' => ['+380', 9, 9],
        'TR' => ['+90', 10, 10],
        'RU' => ['+7', 10, 10],
        'US' => ['+1', 10, 10],
        'CA' => ['+1', 10, 10],
        'MX' => ['+52', 10, 10],
        'BR' => ['+55', 10, 11],
        'AR' => ['+54', 10, 10],
        'CL' => ['+56', 9, 9],
        'CO' => ['+57', 10, 10],
        'PE' => ['+51', 9, 9],
        'AU' => ['+61', 9, 9],
        'NZ' => ['+64', 8, 10],
        'JP' => ['+81', 9, 10],
        'KR' => ['+82', 9, 10],
        'CN' => ['+86', 11, 11],
        'HK' => ['+852', 8, 8],
        'SG' => ['+65', 8, 8],
        'IN' => ['+91', 10, 10],
        'ID' => ['+62', 9, 12],
        'MY' => ['+60', 9, 10],
        'PH' => ['+63', 10, 10],
        'TH' => ['+66', 9, 9],
        'VN' => ['+84', 9, 10],
        'AE' => ['+971', 9, 9],
        'IL' => ['+972', 9, 9],
        'EG' => ['+20', 10, 10],
        'MA' => ['+212', 9, 9],
        'NG' => ['+234', 10, 10],
        'KE' => ['+254', 9, 9],
        'ZA' => ['+27', 9, 9],
    ];

    /** The shortest and longest national number E.164 allows, for a country this table has never heard of. */
    public const E164_MIN_DIGITS = 4;

    public const E164_MAX_DIGITS = 15;

    /**
     * Every country in the table, in the order it is written — the common ones
     * first, so a select opens on something a user is likely to want.
     *
     * @return array<int, DialingCode>
     */
    public static function all(): array
    {
        return array_map(
            static fn (string $country): DialingCode => self::of($country),
            array_keys(self::CODES),
        );
    }

    /**
     * The named countries, in the order they were asked for.
     *
     * @param  array<int, string>  $countries  ISO 3166-1 alpha-2 codes
     * @return array<int, DialingCode>
     */
    public static function only(array $countries): array
    {
        $known = array_filter(
            array_map('strtoupper', $countries),
            static fn (string $country): bool => isset(self::CODES[$country]),
        );

        return array_map(self::of(...), array_values($known));
    }

    /** One country's entry, or null when the table does not carry it. */
    public static function find(string $country): ?DialingCode
    {
        return isset(self::CODES[strtoupper($country)]) ? self::of(strtoupper($country)) : null;
    }

    /**
     * The country a written international number belongs to — the longest
     * matching prefix, so `+420` wins over `+4` and `+1` never swallows `+1868`.
     */
    public static function match(string $number): ?DialingCode
    {
        $digits = '+'.preg_replace('/\D/', '', $number);
        $best = null;

        foreach (self::CODES as $country => [$dialingCode]) {
            if (! str_starts_with($digits, $dialingCode)) {
                continue;
            }

            if ($best === null || strlen($dialingCode) > strlen($best->dialingCode)) {
                $best = self::of($country);
            }
        }

        return $best;
    }

    /**
     * A number written the way it is read: the prefix, then the national part in
     * groups of three. An unknown prefix is returned as it came — better an
     * unformatted number than a wrongly grouped one.
     */
    public static function written(string $number): string
    {
        $code = self::match($number);
        $digits = (string) preg_replace('/\D/', '', $number);

        if ($code === null || $digits === '') {
            return trim($number);
        }

        return trim($code->dialingCode.' '.$code->write(substr($digits, strlen($code->dialingCode) - 1)));
    }

    private static function of(string $country): DialingCode
    {
        [$dialingCode, $minDigits, $maxDigits] = self::CODES[$country];

        return new DialingCode($country, $dialingCode, $minDigits, $maxDigits);
    }
}
