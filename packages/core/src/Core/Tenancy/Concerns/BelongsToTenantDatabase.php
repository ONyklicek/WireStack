<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Concerns;

use NyonCode\WireCore\Core\Tenancy\Isolation\DatabaseIsolation;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;

/**
 * A model that lives in its tenant's own database ({@see DatabaseIsolation}).
 *
 * The counterpart of {@see BelongsToTenant} for the other isolation: no column
 * and no scope — the connection is the boundary. Read through the connection
 * `wire-core.tenancy.database.connection` names, which the current tenant
 * points at its database.
 */
trait BelongsToTenantDatabase
{
    public function getConnectionName(): ?string
    {
        return app(TenantDatabases::class)->connection();
    }
}
