<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireCore\Foundation\Enums\LabelStyle;

/**
 * Thrown when `wire-core.labels.from_name` names a style that does not exist.
 *
 * Falling back to the default would hide a typo behind labels that look almost
 * right — "Plan Period" where the application asked for "Plan period" — on
 * every screen, with nothing pointing at the config line.
 */
final class LabelStyleException extends InvalidArgumentException implements WireException
{
    public static function unknown(mixed $style): self
    {
        $given = is_scalar($style) ? (string) $style : get_debug_type($style);

        return new self(
            "Unknown label style [{$given}] in wire-core.labels.from_name. Use one of ["
            .implode(', ', LabelStyle::values()).'], a LabelStyle case, or an invokable class name.'
        );
    }
}
