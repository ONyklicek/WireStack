<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;

/**
 * The default answer to "which tenant": whatever was entered.
 *
 * Resolves {@see CurrentTenant} on every call rather than holding one: the
 * resolver lives inside the `Tenancy` singleton, and the holder is scoped — a
 * reference kept from construction would be the first request's holder for the
 * life of the process.
 *
 * Nothing entered answers null, exactly as `NullTenantResolver` did, so an
 * application that never enters a tenant sees no change. An application that
 * binds a resolver of its own keeps it; binding one is the opt-out.
 */
final class CurrentTenantResolver implements TenantResolver
{
    public function resolve(): int|string|null
    {
        return app(CurrentTenant::class)->key();
    }
}
