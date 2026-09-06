<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCode;
use NyonCode\WireCore\Foundation\ValueObjects\DialingCodes;

/**
 * Validates a written international phone number.
 *
 * The number is checked in three steps, and each answers a different mistake:
 * it must be international at all (a leading `+`, because a bare national
 * number means nothing to a system that stores one column), its prefix must be
 * one the field offers, and the national part behind that prefix must have as
 * many digits as the country issues.
 *
 * A country outside {@see DialingCodes}'s table is not rejected — it falls back
 * to E.164's own bounds, which is the most any table-free check can say.
 *
 * Spacing is ignored throughout: `+420 123 456 789` and `+420123456789` are the
 * same number, and the field writes the spaced form on purpose.
 */
final class PhoneNumber implements ValidationRule
{
    /**
     * @param  array<int, DialingCode>  $allowed  the countries the field offers; empty means every country
     */
    public function __construct(private readonly array $allowed = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = is_scalar($value) ? trim((string) $value) : '';
        $digits = (string) preg_replace('/\D/', '', $number);

        if (! str_starts_with($number, '+') || $digits === '') {
            $fail('wire-forms::fields.phone.invalid')->translate();

            return;
        }

        $code = DialingCodes::match($number);

        if ($code !== null && $this->allowed !== [] && ! $this->isAllowed($code)) {
            $fail('wire-forms::fields.phone.country')->translate();

            return;
        }

        if ($code === null) {
            // No entry to check against, so only E.164's own limits apply.
            if (strlen($digits) < DialingCodes::E164_MIN_DIGITS || strlen($digits) > DialingCodes::E164_MAX_DIGITS) {
                $fail('wire-forms::fields.phone.invalid')->translate();
            }

            return;
        }

        $national = strlen($digits) - (strlen($code->dialingCode) - 1);

        if (! $code->accepts($national)) {
            $fail('wire-forms::fields.phone.length')->translate([
                'min' => $code->minDigits,
                'max' => $code->maxDigits,
            ]);
        }
    }

    private function isAllowed(DialingCode $code): bool
    {
        foreach ($this->allowed as $allowed) {
            // The prefix is what the user picked; two countries sharing one
            // (the +1 plan) are the same choice as far as a number goes.
            if ($allowed->dialingCode === $code->dialingCode) {
                return true;
            }
        }

        return false;
    }
}
