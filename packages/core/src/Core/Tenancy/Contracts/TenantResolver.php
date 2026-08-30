<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Contracts;

use NyonCode\WireCore\Core\Tenancy\TenantScope;

/**
 * Which tenant the current context belongs to.
 *
 * The application's answer, never the framework's: a tenant may come from the
 * subdomain, a column on the authenticated user, a path segment, or a value a
 * console command was told. What the framework owns is what happens when the
 * answer is **null**, and that is the whole of the security story — see
 * {@see TenantScope}.
 *
 * Returning null is not an error. It is the ordinary state before login, on a
 * queue worker, and in a console command. It is also the state in which a
 * tenant-scoped query must return **nothing**.
 */
interface TenantResolver
{
    /**
     * @return int|string|null The tenant key, or null when there is no tenant.
     */
    public function resolve(): int|string|null;
}
