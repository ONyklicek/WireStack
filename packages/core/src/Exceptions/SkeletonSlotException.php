<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;
use NyonCode\WireCore\Foundation\View\Skeleton;

/**
 * Thrown when a {@see Skeleton} is filled without a value for a slot its compiled
 * template actually contains.
 *
 * The alternative is silence: the sentinel stays in the markup and ships to the
 * browser as visible gibberish in one cell of one column, for the one record whose
 * shape hit the missing branch. That is the failure this class exists to make loud —
 * it is always a compose-time mistake in the caller, never bad user data.
 */
final class SkeletonSlotException extends InvalidArgumentException implements WireException
{
    public static function missing(string $slot): self
    {
        return new self(
            "Skeleton slot [{$slot}] is present in the compiled template but no value "
            .'was given for it. Pass every slot the template was compiled with to '
            .'fill() — an unfilled slot would render its sentinel into the page.'
        );
    }
}
