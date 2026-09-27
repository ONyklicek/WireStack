<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Exceptions;

use InvalidArgumentException;
use NyonCode\WireAdmin\Enums\NavigationShape;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/** A navigation shape the shell does not have. */
final class NavigationShapeException extends InvalidArgumentException implements WireException
{
    public static function unknown(string $value): self
    {
        $shapes = implode("', '", array_map(fn (NavigationShape $shape): string => $shape->value, NavigationShape::cases()));

        return new self(
            "[{$value}] is not a navigation shape. `wire-admin.layout.navigation` and the layout's "
            ."`navigation` attribute take '{$shapes}'."
        );
    }
}
