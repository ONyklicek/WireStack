<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use InvalidArgumentException;
use NyonCode\WireCore\Foundation\Contracts\WireException;

/**
 * Thrown when a retention period would keep nothing.
 *
 * Pruning deletes what is older than *now minus N days*. At zero that is
 * everything written before this second; a negative N reaches into the future
 * and is everything, full stop. For an audit trail — the one record whose whole
 * value is that it cannot be rewritten — neither is a retention period, and both
 * are what a misconfigured scheduler produces: an unset variable, arithmetic on a
 * date, a typo. Refusing is the only answer that cannot be regretted.
 */
final class InvalidRetentionException extends InvalidArgumentException implements WireException
{
    public static function keepsNothing(int $days): self
    {
        return new self(
            "An audit retention of [{$days}] day(s) would prune every entry. Pass a whole number "
            .'of days, at least 1, or leave retention unset to keep the trail for ever.'
        );
    }
}
