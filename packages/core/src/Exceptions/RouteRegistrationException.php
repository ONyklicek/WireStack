<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use LogicException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * A route macro called where its routes cannot keep the names they are linked by.
 */
final class RouteRegistrationException extends LogicException implements WireException
{
    /** @param  array<int, string>  $known */
    public static function unknownGroup(string $key, array $known): self
    {
        return new self(
            "No route group is registered as [{$key}]. Known: [".implode(', ', $known).'] — a group exists once '
            .'the package that brings it is installed.'
        );
    }

    public static function registeredTwice(string $key, string $name): self
    {
        return new self(
            "The route [{$name}] of the [{$key}] group is registered twice, at two addresses — by "
            .'wire-core.routes.groups and by Route::wire() in a route file, or by two calls. The second would '
            .'take the name of the first, and every link to it; keep one, or give one a zone.'
        );
    }

    public static function namedGroup(string $macro, string $prefix): self
    {
        return new self(
            "Route::{$macro}() was called inside a group named [{$prefix}]. Its routes are looked up by the "
            .'names it gives them — from e-mails, screens and zones — which the group would rename. Call it '
            .'outside any name(): Route::middleware([…])->prefix(…)->group(fn () => Route::'.$macro.'()).'
        );
    }
}
