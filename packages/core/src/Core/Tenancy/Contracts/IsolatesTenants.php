<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;

/**
 * How one tenant's data is kept apart from another's (ADR 0040 §2).
 *
 * Called by {@see CurrentTenant} and by nothing else: entering a tenant is one
 * act, and an isolation that could be entered from two places could be left in
 * a state neither of them expected. Rows kept apart by a column need nothing
 * here — the tenant scope reads the current tenant itself — while a database
 * per tenant points its connection at the tenant's database on `enter()` and
 * back at nothing on `leave()`.
 *
 * The size of it is deliberate: a bridge to a package that owns the harder parts
 * of database-per-tenant (stancl/tenancy) is these two methods.
 */
interface IsolatesTenants
{
    /** Make this tenant's data the data the application sees. */
    public function enter(Model $tenant): void;

    /** See no tenant's data at all. */
    public function leave(): void;
}
