<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\ValueObjects;

/**
 * One country's telephone dialling vocabulary: its international prefix and how
 * many digits the national number behind that prefix has.
 *
 * The flag is derived rather than stored — an ISO 3166-1 alpha-2 code maps
 * character by character onto the Unicode regional-indicator block, so `CZ`
 * becomes 🇨🇿 without a table to keep in step with the codes.
 */
final class DialingCode
{
    public function __construct(
        public readonly string $country,
        public readonly string $dialingCode,
        public readonly int $minDigits,
        public readonly int $maxDigits,
    ) {}

    /** The country's flag as a regional-indicator pair, computed from its ISO code. */
    public function flag(): string
    {
        $flag = '';

        foreach (str_split(strtoupper($this->country)) as $letter) {
            $flag .= mb_chr(0x1F1E6 + (ord($letter) - ord('A')), 'UTF-8');
        }

        return $flag;
    }

    /** How the option reads in the country select — the flag, then the prefix. */
    public function label(): string
    {
        return $this->flag().' '.$this->dialingCode;
    }

    /**
     * A national number written in groups of three, the trailing single digit
     * joining the group before it — `212 555 1234` reads as a number,
     * `212 555 123 4` reads as a typo.
     *
     * The browser mirrors this in `phone-input.js`; the two must write the same
     * number, whichever side rendered it.
     */
    public function write(string $digits): string
    {
        $groups = str_split($digits, 3);

        if (count($groups) > 1 && strlen(end($groups)) === 1) {
            $last = array_pop($groups);
            $groups[count($groups) - 1] .= $last;
        }

        return implode(' ', $groups);
    }

    /** Whether a national number of this many digits is one this country issues. */
    public function accepts(int $digits): bool
    {
        return $digits >= $this->minDigits && $digits <= $this->maxDigits;
    }
}
