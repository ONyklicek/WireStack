<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\PendingDispatch;
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

    /**
     * Work inside one tenant for the length of a callback, and come back out.
     *
     * The one way a command, a seeder, a job or a test enters a tenant: it
     * restores whatever was current before — another tenant, or none — even
     * when the callback throws, so nothing is left entered by accident. Nested
     * calls unwind in order.
     *
     * **A job the callback returns is dispatched here, inside the tenant.**
     * `Job::dispatch()` gives back a `PendingDispatch` that queues when it is
     * destroyed, and one returned out of `fn () => Job::dispatch()` would be
     * destroyed by the caller — after the tenant is left, carrying none. This
     * holds the only reference to it, so dropping it here queues it now; the
     * return value is then null, since the job is already on its way.
     *
     * @template TReturn
     *
     * @param  Closure(Model): TReturn  $callback  Receives the tenant.
     * @return TReturn
     */
    public function runAs(Model $tenant, Closure $callback): mixed
    {
        $current = app(CurrentTenant::class);
        $previous = $current->get();

        $current->enter($tenant);

        try {
            $result = $callback($tenant);

            if ($result instanceof PendingDispatch) {
                unset($result);

                return null;
            }

            return $result;
        } finally {
            $previous === null ? $current->leave() : $current->enter($previous);
        }
    }
}
