<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when an icon set is registered under a prefix that would make icon
 * resolution ambiguous.
 *
 * Both messages spell out the working form, because the caller is registering a
 * set once at boot and has no other feedback loop.
 */
final class IconSetRegistrationException extends InvalidArgumentException implements WireException
{
    public static function prefixReserved(): self
    {
        return new self(
            'Only the bundled Heroicons set is available unprefixed. Register additional '
            .'icon sets under a unique prefix, e.g. registerIconSet($set, "lucide"), and '
            .'reference their icons as "lucide:home".'
        );
    }

    public static function prefixContainsColon(string $prefix): self
    {
        return new self(
            "Icon set prefix [{$prefix}] must not contain a colon; the colon separates "
            .'the prefix from the icon name (e.g. "lucide:home").'
        );
    }
}
