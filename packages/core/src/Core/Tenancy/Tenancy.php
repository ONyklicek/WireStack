<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;

/**
 * Whether tenancy is on, and who the current tenant is.
 *
 * One place answers both, because the two questions are the same decision seen
 * from either side: a scope that read the config in one method and the resolver
 * in another could disagree with itself, and disagreeing about tenancy means
 * showing one tenant another's rows.
 *
 * **Opt-in, and strict once on.** Off, nothing here does anything. On, every
 * tenant-scoped model is constrained — and when no tenant resolves, constrained
 * to *nothing*. That direction is the whole point: the failure mode of a tenancy
 * bug is a leak, so the failure mode of this code is an empty page.
 */
final class Tenancy
{
    public function __construct(private readonly TenantResolver $resolver) {}

    /**
     * Whether tenancy is switched on at all.
     *
     * Opt-in because most applications have one tenant and scoping them would
     * be a `where` clause bought for nothing. Read per call rather than cached:
     * a test that flips the config mid-run must see the change, and this is not
     * a hot enough path to pay for a memo with a stale-value bug.
     */
    public function enabled(): bool
    {
        return (bool) config('wire-core.tenancy.enabled', false);
    }

    /** The column tenant-scoped models are constrained on. */
    public function column(): string
    {
        return (string) config('wire-core.tenancy.column', 'tenant_id');
    }

    /**
     * The current tenant key, or null when there is none.
     *
     * Null is an ordinary state — before login, on a worker, in a console
     * command — and it is the state a scope must treat as "nothing", never as
     * "everything".
     */
    public function current(): int|string|null
    {
        return $this->enabled() ? $this->resolver->resolve() : null;
    }

    /**
     * Whether a query must be constrained to nothing.
     *
     * The fail-safe, named so a test can assert it directly rather than through
     * a row count: tenancy on and no tenant resolved is the one combination that
     * must return an empty set.
     */
    public function shouldBlockEverything(): bool
    {
        return $this->enabled() && $this->resolver->resolve() === null;
    }
}
