<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when an icon set cannot be registered as declared — under a prefix that
 * would make icon resolution ambiguous, or from a config entry that does not
 * name a set at all.
 *
 * Every message spells out the working form, because the caller is registering a
 * set once at boot and has no other feedback loop. That is also why the config
 * cases throw rather than skipping: an entry that is quietly ignored costs a set
 * of icons that render as the missing-icon placeholder, with nothing anywhere
 * connecting the two. Config is read at boot, so none of these can reach a
 * request.
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

    /**
     * A non-default set declared under a numeric key, so it has no prefix to be
     * addressed by.
     */
    public static function prefixNotAString(string $class): self
    {
        return new self(
            "Icon set [{$class}] must be configured under a string prefix key in "
            .'wire-core.icons.sets (e.g. \'lucide\' => LucideIconSet::class). Only the '
            .'set matching wire-core.icons.default_set may be unprefixed.'
        );
    }

    /**
     * Something in `wire-core.icons.sets` that cannot be an icon set.
     *
     * Skipped until now, and that is the failure this replaces: a typo in a class
     * name meant the set simply did not exist, and every icon addressed through
     * its prefix rendered the placeholder instead.
     */
    public static function notAnIconSet(mixed $class, string $contract): self
    {
        $name = is_string($class) ? $class : get_debug_type($class);

        $reason = is_string($class) && (class_exists($class) || interface_exists($class))
            ? "it does not implement [{$contract}]"
            : 'it is not a class that exists';

        return new self(
            "[{$name}] is listed in wire-core.icons.sets but cannot be registered: {$reason}."
        );
    }

    /**
     * An entry in `wire-core.icons.paths` that is not a directory of SVG files.
     *
     * Same reasoning as {@see notAnIconSet()}: silently ignored, a mistyped path
     * costs every icon in that folder and says nothing.
     */
    public static function iconDirectoryUnreadable(mixed $path): self
    {
        $name = is_string($path) ? "[{$path}]" : get_debug_type($path);

        return new self(
            "{$name} is listed in wire-core.icons.paths but is not a readable directory. "
            .'Each entry must be a path to a directory of .svg files; a string key is used '
            .'as a name prefix for the icons found there.'
        );
    }
}
