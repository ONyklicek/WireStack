<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a chart widget is handed data or options it cannot render.
 */
final class InvalidChartDataException extends InvalidArgumentException implements WireException
{
    /**
     * @param  array<int, string>  $allowed
     */
    public static function unknownType(string $type, array $allowed): self
    {
        return new self(sprintf('Invalid chart type [%s]. Allowed: %s.', $type, implode(', ', $allowed)));
    }

    /**
     * @param  array<int, string>  $allowed
     */
    public static function unknownVariant(string $variant, array $allowed): self
    {
        return new self(sprintf('Invalid chart variant [%s]. Allowed: %s.', $variant, implode(', ', $allowed)));
    }

    public static function notChartItems(string $expected): self
    {
        return new self('BarChartWidget::items() expects an array of '.$expected.' instances.');
    }

    public static function percentageOutOfRange(float $percentage): self
    {
        return new self("Chart item percentage must be between 0 and 100, [{$percentage}] given.");
    }
}
