<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A menu entry whose `parent()` cannot be honoured.
 *
 * Refused rather than resolved, by the rule the catalogue follows for two
 * sources claiming one key: every quiet fallback here would be a row that went
 * missing or turned up somewhere nobody put it, and a menu that lost a row is
 * noticed on the day that row mattered. Each case is a declaration error, so it
 * surfaces on the first render of any menu rather than on one user's.
 */
final class NavigationParentException extends RuntimeException implements WireException
{
    public static function unknown(string $key, string $parent): self
    {
        return new self(
            "The navigation entry [{$key}] names [{$parent}] as its parent, and nothing is ".
            'registered under that key. A parent is a registered key or the class '.
            'registered under it.'
        );
    }

    public static function itself(string $key): self
    {
        return new self("The navigation entry [{$key}] names itself as its parent.");
    }

    public static function tooDeep(string $key, string $parent, string $grandparent): self
    {
        return new self(
            "The navigation entry [{$key}] names [{$parent}] as its parent, which already sits ".
            "under [{$grandparent}]. A menu nests one level: put [{$key}] under [{$grandparent}], ".
            'or show the third level on the page itself, as tabs.'
        );
    }
}
