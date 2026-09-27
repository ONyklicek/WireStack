<?php

declare(strict_types=1);

namespace NyonCode\WirePanels\Contracts;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WirePanels\Concerns\InteractsWithTenants;
use NyonCode\WirePanels\Http\Middleware\IdentifyTenant;

/**
 * A user who belongs to tenants — companies — and may enter only those.
 *
 * Implemented on the application's user model, beside {@see HasPreferredZone},
 * because membership is a fact about the person (ADR 0040 §5). The tenant
 * zone's middleware ({@see IdentifyTenant}) asks it on every request; a user
 * model without it inside a tenant zone is refused rather than read as "may
 * enter every tenant". {@see InteractsWithTenants} answers all three over an
 * ordinary many-to-many.
 */
interface HasTenants
{
    /**
     * The tenants this person belongs to — what a switcher offers.
     *
     * @return iterable<int, Model>
     */
    public function getTenants(): iterable;

    /** Whether this person may work in this tenant. Asked on every request inside one. */
    public function canAccessTenant(Model $tenant): bool;

    /** Where the tenant zone's own address sends this person, or null for nowhere. */
    public function getDefaultTenant(): ?Model;
}
