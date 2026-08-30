<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;

/**
 * The bound default: there is no tenant.
 *
 * Deliberately not "the authenticated user's tenant" — the framework does not
 * know which column that is, and guessing would be a guess about who may see
 * what. An application that turns tenancy on binds its own resolver; until it
 * does, a tenant-scoped model returns nothing, which is the safe direction to
 * fail in.
 */
final class NullTenantResolver implements TenantResolver
{
    public function resolve(): int|string|null
    {
        return null;
    }
}
