<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WirePanels\Pages\Page;

/** A page that cannot be registered as one. */
final class PageRegistrationException extends InvalidArgumentException implements WireException
{
    public static function notAPage(string $class): self
    {
        return new self("[{$class}] is registered as a page but does not extend ".Page::class.'.');
    }

    public static function duplicateKey(string $key, string $existing, string $incoming): self
    {
        return new self(
            "Pages [{$existing}] and [{$incoming}] both claim the key [{$key}], which is their URL and ".
            'their menu entry. Give one of them `protected static ?string $slug`.'
        );
    }
}
