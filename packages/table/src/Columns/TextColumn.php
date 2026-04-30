<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Columns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class TextColumn extends Column
{
    protected ?string $fontFamily = null;

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

    public function fontFamily(?string $family): static
    {
        $this->fontFamily = $family;

        return $this;
    }

    public function getFontFamily(): ?string
    {
        return $this->fontFamily;
    }

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

    public function date(?string $format = 'd.m.Y'): static
    {
        $this->date = true;
        $this->dateFormat = $format;

        return $this;
    }

    public function dateTime(?string $format = 'd.m.Y H:i'): static
    {
        $this->dateTime = true;
        $this->dateFormat = $format;

        return $this;
    }

    public function since(): static
    {
        $this->since = true;

        return $this;
    }

    public function formatValue(mixed $value, Model $record): string
    {
        if ($value === null || $value === '') {
            return $this->getPlaceholder();
        }

        // Date / datetime
        if (($this->date || $this->dateTime) && $value) {
            $value = $this->since
                ? ($value instanceof Carbon
                    ? $value->diffForHumans()
                    : Carbon::parse($value)->diffForHumans())
                : ($value instanceof Carbon
                    ? $value->format($this->dateFormat)
                    : Carbon::parse($value)->format($this->dateFormat));
        }

        // 💰 Money (má prioritu)
        if ($this->money && is_numeric($value)) {
            $decimals = $this->currency === 'Kč' ? 0 : 2;

            $value =
                number_format((float) $value, $decimals, ',', ' ').
                ' '.
                $this->currency;

            return parent::formatValue($value, $record);
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

        return parent::formatValue($value, $record);
    }
}
