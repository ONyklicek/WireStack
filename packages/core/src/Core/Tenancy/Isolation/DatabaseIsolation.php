<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Isolation;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\Concerns\BelongsToTenantDatabase;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\TenantDatabases;

/**
 * One database per tenant (ADR 0040 §2).
 *
 * Entering points the tenant connection at the tenant's database and purges
 * the connection already made, so the next query opens the right one; leaving
 * points it at no database at all. Tenant-owned models use
 * {@see BelongsToTenantDatabase}; tenants, users and memberships stay on the
 * application's own connection.
 *
 * **No tenant, no database.** With nothing entered the connection names none,
 * and the first query on it throws — the same direction as the column scope's
 * empty result, and louder, which suits a mistake that would otherwise read
 * another company's rows.
 */
final readonly class DatabaseIsolation implements IsolatesTenants
{
    public function __construct(private TenantDatabases $databases) {}

    public function enter(Model $tenant): void
    {
        $this->databases->point($this->databases->nameFor($tenant));
    }

    public function leave(): void
    {
        $this->databases->point(null);
    }
}
