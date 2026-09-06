<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Validation\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use NyonCode\WireCore\Foundation\ValueObjects\MoneyFormat;

/**
 * Validates the amount behind a written figure.
 *
 * A money field's state is text — `1 234,50` — so Laravel's own `numeric`,
 * `min` and `max` would judge the separators rather than the amount. This rule
 * parses the text with the field's own {@see MoneyFormat} first and compares
 * the number, which is the only value the user meant.
 *
 * The bounds are reported back in the same format they were typed in, so an
 * error message reads in the field's currency rather than in raw floats.
 */
final class MoneyAmount implements ValidationRule
{
    public function __construct(
        private readonly MoneyFormat $format,
        private readonly ?float $min = null,
        private readonly ?float $max = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $amount = $this->format->parse(is_scalar($value) ? $value : null);

        if ($amount === null) {
            $fail('wire-forms::fields.money.invalid')->translate();

            return;
        }

        if ($this->min !== null && $amount < $this->min) {
            $fail('wire-forms::fields.money.min')->translate(['min' => $this->format->format($this->min)]);

            return;
        }

        if ($this->max !== null && $amount > $this->max) {
            $fail('wire-forms::fields.money.max')->translate(['max' => $this->format->format($this->max)]);
        }
    }
}
