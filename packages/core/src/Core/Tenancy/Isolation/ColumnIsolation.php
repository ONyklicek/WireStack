<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Isolation;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\TenantScope;

/**
 * Every tenant in one database, kept apart by a column — the default.
 *
 * Nothing to do on entering or leaving, and that is the design rather than a
 * gap: {@see TenantScope} asks for the current tenant on every query, so there
 * is no state here to set and none to forget to clear.
 */
final class ColumnIsolation implements IsolatesTenants
{
    public function enter(Model $tenant): void {}

    public function leave(): void {}
}
