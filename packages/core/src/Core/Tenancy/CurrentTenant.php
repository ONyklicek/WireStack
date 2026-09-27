<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use Illuminate\Database\Eloquent\Model;
use NyonCode\WireCore\Core\Tenancy\Contracts\IsolatesTenants;
use NyonCode\WireCore\Core\Tenancy\Events\TenantEntered;
use NyonCode\WireCore\Core\Tenancy\Events\TenantLeft;

/**
 * The tenant this request — or this job, or this callback — is working in.
 *
 * The one holder of that answer (ADR 0040 §1). The tenant scope reads it through
 * {@see CurrentTenantResolver}, a tenant zone's middleware sets it, and code of
 * the application's own asks it; a second place keeping "the current tenant"
 * is the defect the users module's per-session team had, and not a pattern.
 *
 * **Scoped, not a singleton.** Bound with `scoped()`, so an Octane request and a
 * queued job each start with no tenant rather than inheriting the last one's —
 * which under the fail-safe means an empty result, never someone else's rows.
 *
 * Entering drives the isolation ({@see IsolatesTenants}) and then announces
 * itself ({@see TenantEntered}, {@see TenantLeft}) for state that hangs off the
 * tenant without being a query; entering the tenant already entered does
 * nothing, and entering another leaves the first.
 */
final class CurrentTenant
{
    private ?Model $tenant = null;

    public function __construct(private readonly IsolatesTenants $isolation) {}

    public function enter(Model $tenant): void
    {
        if ($this->tenant !== null && $this->is($tenant)) {
            return;
        }

        if ($this->tenant !== null) {
            $this->leave();
        }

        $this->isolation->enter($tenant);
        $this->tenant = $tenant;

        event(new TenantEntered($tenant));
    }

    public function leave(): void
    {
        if ($this->tenant === null) {
            return;
        }

        $left = $this->tenant;

        $this->isolation->leave();
        $this->tenant = null;

        event(new TenantLeft($left));
    }

    public function get(): ?Model
    {
        return $this->tenant;
    }

    /** The current tenant's key — what the scope constrains by — or null for none. */
    public function key(): int|string|null
    {
        $key = $this->tenant?->getKey();

        return is_int($key) || is_string($key) ? $key : null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /** Whether this is the tenant being worked in — by class and key, not by object identity. */
    public function is(Model $tenant): bool
    {
        return $this->tenant !== null
            && $tenant::class === $this->tenant::class
            && $this->tenant->getKey() === $tenant->getKey();
    }
}
