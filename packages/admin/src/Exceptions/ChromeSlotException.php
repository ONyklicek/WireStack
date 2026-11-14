<?php

declare(strict_types=1);

namespace NyonCode\WireAdmin\Exceptions;

use InvalidArgumentException;
use NyonCode\WireAdmin\Enums\ChromeSlot;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/** A place in the shell's chrome that does not exist. */
final class ChromeSlotException extends InvalidArgumentException implements WireException
{
    public static function unknown(string $value, string $setting): self
    {
        $places = implode("', '", array_map(fn (ChromeSlot $slot): string => $slot->value, ChromeSlot::cases()));

        return new self("[{$value}] is not a place in the shell. `wire-admin.layout.{$setting}` takes '{$places}'.");
    }
}
