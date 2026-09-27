<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy\Events;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\CurrentTenant;

/**
 * A tenant has just become the one being worked in ({@see CurrentTenant::enter()}).
 *
 * For what hangs off the tenant and is not a query — the permission layer's
 * current team, a cache prefix, a filesystem root — so each owner sets its own
 * state from the one moment the tenant changes, rather than from middleware
 * that may run before the tenant is known.
 */
final readonly class TenantEntered
{
    public function __construct(public Model $tenant) {}
}
