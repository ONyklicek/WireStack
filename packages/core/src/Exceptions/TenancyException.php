<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Exceptions;

use NyonCode\WireCore\Foundation\Contracts\WireException;
use RuntimeException;

/**
 * A write that cannot be attributed to a tenant.
 *
 * Loud rather than silent, and that direction is deliberate: the alternative to
 * refusing is writing a row with a null tenant, which every scoped query then
 * hides from everyone — a record that exists, cost the user their work, and is
 * gone. Failing at the write is recoverable; failing at the read is not.
 */
final class TenancyException extends RuntimeException implements WireException
{
    public static function noTenantToAssign(string $model): self
    {
        return new self(
            "Cannot create [{$model}]: tenancy is enabled but no tenant resolved, ".
            'so the row could not be attributed to one. A row written without a '.
            'tenant is invisible to every scoped query afterwards, which is why '.
            'this refuses instead. Bind a TenantResolver that answers here, or '.
            'set the tenant column explicitly before saving.'
        );
    }
}
