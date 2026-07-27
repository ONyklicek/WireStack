<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a min/max date bound cannot be read as a date at all.
 *
 * A bound is compared as a plain string by both the browser and the custom
 * picker, so an unreadable one does not degrade — it silently disables every
 * day or none. Failing at the point the bound is declared beats debugging a
 * calendar that looks fine and behaves wrongly.
 */
final class InvalidDateBoundaryException extends InvalidArgumentException implements WireException
{
    public static function unreadable(string $value): self
    {
        return new self(
            "Date bound [{$value}] could not be read as a date. Pass a DateTimeInterface, "
            .'or a string any of PHP\'s date parsers understands ("2026-07-10", "10.07.2026", '
            .'"today", "+1 week").'
        );
    }

    public static function unsupportedType(string $type): self
    {
        return new self(
            "Date bound of type [{$type}] is not supported. Pass a string, a DateTimeInterface, "
            .'a Closure returning either, or null.'
        );
    }
}
