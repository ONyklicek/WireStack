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

    public static function unknownIsolation(string $value): self
    {
        return new self(
            "[{$value}] is not a tenant isolation. `wire-core.tenancy.isolation` takes 'column', "
            ."'database' or the name of a class implementing NyonCode\\WireCore\\Core\\Tenancy\\Contracts\\IsolatesTenants."
        );
    }

    public static function tenantGone(string $model, mixed $key): self
    {
        $key = is_scalar($key) ? (string) $key : get_debug_type($key);

        return new self(
            "A queued job was dispatched in the tenant [{$model}:{$key}], which no longer exists. "
            .'It fails rather than running in no tenant, where every scoped query would return nothing.'
        );
    }

    public static function noTenantConnection(string $connection): self
    {
        return new self(
            "Database isolation reads tenant data through the connection [{$connection}], which "
            .'`database.connections` does not define. Add it — usually a copy of the default one — '
            .'or name another in `wire-core.tenancy.database.connection`.'
        );
    }

    public static function noTenantModel(): self
    {
        return new self('`wire-core.tenancy.model` names no Eloquent model, so there is no tenant to find.');
    }
}
