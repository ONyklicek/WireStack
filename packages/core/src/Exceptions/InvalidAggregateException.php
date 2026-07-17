<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when an aggregate clause is not a valid one.
 *
 * Each message names the closed vocabulary it rejected against, because the
 * caller is an author who reached for a function or strategy that does not
 * exist and needs to know what does.
 */
final class InvalidAggregateException extends InvalidArgumentException implements WireException
{
    /**
     * @param  array<int, string>  $valid
     */
    public static function unknownFunction(string $function, array $valid): self
    {
        return new self("Invalid aggregate function [{$function}]. Valid: ".implode(', ', $valid));
    }

    /**
     * @param  array<int, string>  $valid
     */
    public static function unknownStrategy(string $strategy, array $valid): self
    {
        return new self("Invalid aggregate strategy [{$strategy}]. Valid: ".implode(', ', $valid));
    }

    public static function columnRequired(string $function): self
    {
        return new self("Aggregate function [{$function}] requires a column.");
    }
}
