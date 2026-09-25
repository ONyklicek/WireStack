<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * A board declared too little to be drawn.
 *
 * Each case would otherwise surface as a query error or an empty board, and an
 * empty board reads as "nothing to do" rather than as a mistake.
 */
final class BoardConfigurationException extends InvalidArgumentException implements WireException
{
    public static function noModel(): self
    {
        return new self('The board names no model. Call ->model(Task::class) in board().');
    }

    public static function noColumn(): self
    {
        return new self('The board names no column to group by. Call ->groupBy(\'status\') in board().');
    }

    public static function notAnEnum(string $class): self
    {
        return new self("[{$class}] is not a backed enum, so its cases cannot be lanes. Pass Lane objects, value => label pairs, or a backed enum.");
    }
}
