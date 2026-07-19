<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Carbon\Carbon;
use NyonCode\WireCore\Foundation\Support\EnumResolver;

/**
 * Canonical numeric / money / date state formatting.
 *
 * Shared by table columns (`WireTable\Columns\TextColumn`) and infolist entries
 * (`WireCore\Infolists\Components\TextEntry`) so the same `money()`, `numeric()`,
 * `date()`, `dateTime()` and `since()` vocabulary formats a value identically
 * wherever it is displayed.
 *
 * The trait deliberately owns only value transformation. Surface concerns —
 * placeholder fallback, `limit`, prefix/suffix, escaping — stay with the caller
 * (a column applies them in `Column::formatValue`, an entry in its own view
 * helper), so this concern can be mixed into any display surface.
 */
trait FormatsState
{
    protected bool $money = false;

    protected ?string $currency = null;

    protected bool $numeric = false;

    protected ?int $decimals = null;

    protected ?string $decimalSeparator = null;

    protected ?string $thousandsSeparator = null;

    protected bool $date = false;

    protected bool $dateTime = false;

    protected ?string $dateFormat = null;

    protected bool $since = false;

    /** Format the value as a currency amount (defaults to CZK). */
    public function money(?string $currency = 'CZK'): static
    {
        $this->money = true;
        $this->currency = $currency;

        return $this;
    }

    public function isMoney(): bool
    {
        return $this->money;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    /** Format the value as a number with the given decimals and separators. */
    public function numeric(
        int $decimals = 0,
        ?string $decimalSeparator = ',',
        ?string $thousandsSeparator = ' ',
    ): static {
        $this->numeric = true;
        $this->decimals = $decimals;
        $this->decimalSeparator = $decimalSeparator;
        $this->thousandsSeparator = $thousandsSeparator;

        return $this;
    }

    public function isNumeric(): bool
    {
        return $this->numeric;
    }

    /** Format the value as a date using the given PHP date format. */
    public function date(?string $format = 'd.m.Y'): static
    {
        $this->date = true;
        $this->dateFormat = $format;

        return $this;
    }

    /** Format the value as a date and time using the given PHP date format. */
    public function dateTime(?string $format = 'd.m.Y H:i'): static
    {
        $this->dateTime = true;
        $this->dateFormat = $format;

        return $this;
    }

    /** Format the value as a relative "time ago" difference from now. */
    public function since(): static
    {
        $this->since = true;

        return $this;
    }

    /**
     * Apply date / money / numeric formatting to a raw value.
     *
     * Returns the transformed value (often a string). Money takes priority over
     * numeric; both are only applied to numeric values, so a value already
     * formatted as a date string is left untouched.
     */
    protected function applyNumericAndDateFormatting(mixed $value): mixed
    {
        // Enum and array/JSON casts arrive here as raw instances that cannot be stringified.
        // Collapse to a display-safe value first so every consumer of this concern
        // (table columns, infolist entries) formats a scalar rather than fataling.
        $value = EnumResolver::display($value);

        // Date / datetime
        if (($this->date || $this->dateTime || $this->since) && $value) {
            $value = $this->since
                ? ($value instanceof Carbon
                    ? $value->diffForHumans()
                    : Carbon::parse($value)->diffForHumans())
                : ($value instanceof Carbon
                    ? $value->format($this->dateFormat)
                    : Carbon::parse($value)->format($this->dateFormat));
        }

        // 💰 Money (priority)
        if ($this->money && is_numeric($value)) {
            $decimals = $this->currency === 'Kč' ? 0 : 2;

            return number_format((float) $value, $decimals, ',', ' ').' '.$this->currency;
        }

        // 🔢 Numeric
        if ($this->numeric && is_numeric($value)) {
            $value = number_format(
                (float) $value,
                $this->decimals ?? 0,
                $this->decimalSeparator ?? ',',
                $this->thousandsSeparator ?? ' ',
            );
        }

        return $value;
    }
}
