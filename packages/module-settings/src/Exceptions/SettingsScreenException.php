<?php

declare(strict_types=1);

namespace NyonCode\WireModuleSettings\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * The settings screen was told to draw itself in a way it does not know.
 *
 * `InvalidArgumentException` per ADR 0022: the value came from configuration and
 * is wrong as given. Thrown rather than falling back to the default, because a
 * typo in `wire-module-settings.switcher` that quietly drew links would read as
 * "tabs are not supported" rather than as the one character that is off.
 */
final class SettingsScreenException extends InvalidArgumentException implements WireException
{
    /** @param  array<int, string>  $known */
    public static function unknownSwitcher(mixed $given, array $known): self
    {
        return new self(sprintf(
            '[wire-module-settings.switcher] is %s; it takes %s.',
            is_string($given) ? "'{$given}'" : get_debug_type($given),
            implode(' or ', array_map(static fn (string $s): string => "'{$s}'", $known)),
        ));
    }

    /** @param  array<int, string>  $known */
    public static function unknownWidth(mixed $given, array $known): self
    {
        return new self(sprintf(
            '[wire-module-settings.width] is %s; it takes null or one of %s.',
            is_string($given) ? "'{$given}'" : get_debug_type($given),
            implode(', ', array_map(static fn (string $s): string => "'{$s}'", $known)),
        ));
    }
}
