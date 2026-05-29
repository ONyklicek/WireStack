<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Concerns;

use Carbon\Carbon;
use NyonCode\WireTable\Columns\Column;

/**
 * Trait HasFormatting
 *
 * Shared value formatting logic for columns (money, numbers, dates).
 *
 * @phpstan-require-extends Column
 */
trait HasFormatting
{
    protected static array $formatCache = [];

    /**
     * Format money value with currency.
     */
    public static function formatMoney(mixed $value, string $currency = 'CZK', int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $numValue = (float) $value;
        $formatted = number_format($numValue, $decimals, ',', ' ');

        return match (strtoupper($currency)) {
            'CZK' => "{$formatted} Kč",
            'EUR' => "{$formatted} €",
            'USD' => "\${$formatted}",
            'GBP' => "£{$formatted}",
            default => "{$formatted} {$currency}",
        };
    }

    /**
     * Format numeric value.
     */
    public static function formatNumber(
        mixed $value,
        int $decimals = 0,
        string $decimalSeparator = ',',
        string $thousandsSeparator = ' '
    ): string {
        if ($value === null || $value === '') {
            return '';
        }

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    /**
     * Format date value.
     */
    public static function formatDate(mixed $value, string $format = 'd.m.Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Exception) {
            return (string) $value;
        }
    }

    /**
     * Format datetime value.
     */
    public static function formatDateTime(mixed $value, string $format = 'd.m.Y H:i'): string
    {
        return static::formatDate($value, $format);
    }

    /**
     * Format as "time since" (e.g., "před 2 hodinami").
     */
    public static function formatSince(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (\Exception) {
            return (string) $value;
        }
    }
}
