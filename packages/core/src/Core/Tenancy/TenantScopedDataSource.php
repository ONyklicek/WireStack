<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Core\Tenancy;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorImplementation;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Data\DataSource;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;

/**
 * Tenancy for a source Eloquent's global scope cannot reach.
 *
 * {@see TenantScope} covers every query Eloquent builds, which is every query
 * an ordinary application makes — and none that a `CollectionDataSource` or an
 * API-backed source makes, because those build no query at all. Until this
 * existed the documented answer was "constrain those in the source itself",
 * which is correct and easy to forget, and forgetting it shows up as one
 * tenant's rows on another tenant's screen.
 *
 *   $source = new TenantScopedDataSource(new CollectionDataSource($rows), app(Tenancy::class));
 *
 * **It scopes by adding a filter to the plan, not by filtering results.** Every
 * source already applies `QueryPlan::$filters` — that is what makes a filter
 * work at all — and one that cannot apply this particular filter refuses it out
 * loud (`UnsupportedQueryAspectException`) rather than returning rows it did not
 * constrain. Both outcomes are safe; a decorator that filtered the returned rows
 * itself would be safe for `get()` and quietly wrong for `count()` and
 * `paginate()`, which the source answers without ever handing the rows over.
 *
 * **The fail-safe is the same one `TenantScope` has**, and it is why this is
 * worth a class rather than a note in the docs: tenancy on with no tenant
 * resolved answers with *nothing* — not with everything. The states that
 * produce a null tenant are ordinary (before login, a worker, a console
 * command) and must not be the states in which every row is visible.
 *
 * Tenancy off delegates untouched, so wrapping a source costs nothing in the
 * single-tenant application that is most of them.
 */
final readonly class TenantScopedDataSource implements DataSource
{
    /**
     * @param  DataSource  $inner  The source being constrained.
     * @param  string|null  $column  Which attribute holds the tenant key; defaults to
     *                               the configured tenancy column, so a source that names its
     *                               own does so deliberately.
     */
    public function __construct(
        private DataSource $inner,
        private Tenancy $tenancy,
        private ?string $column = null,
    ) {}

    public function paginate(QueryPlan $plan, PagingRequest $paging): LengthAwarePaginator|Paginator|CursorPaginator
    {
        if ($this->blocked()) {
            // An empty page rather than a refusal: a table on a tenant-less
            // request should render "no records", which is the truth, instead
            // of an error the user cannot act on.
            return new LengthAwarePaginatorImplementation([], 0, max(1, $paging->perPage), $paging->page ?? 1, [
                'pageName' => $paging->pageName,
            ]);
        }

        return $this->inner->paginate($this->scoped($plan), $paging);
    }

    public function get(QueryPlan $plan): Collection
    {
        return $this->blocked() ? new Collection : $this->inner->get($this->scoped($plan));
    }

    public function chunk(QueryPlan $plan, int $size, callable $callback): void
    {
        if ($this->blocked()) {
            return;
        }

        $this->inner->chunk($this->scoped($plan), $size, $callback);
    }

    public function count(QueryPlan $plan): int
    {
        return $this->blocked() ? 0 : $this->inner->count($this->scoped($plan));
    }

    /**
     * One record, and only if it is this tenant's.
     *
     * Resolution takes a key rather than a plan, so the filter cannot be added
     * to anything — the record is fetched and then checked. That check is the
     * reason this method is here at all: without it a tenant reaches another
     * tenant's row by typing its id into a URL, which is the oldest hole of
     * this kind there is.
     */
    public function resolveRecord(int|string $key): ?RecordContract
    {
        if ($this->blocked()) {
            return null;
        }

        $record = $this->inner->resolveRecord($key);

        return $record !== null && $this->belongsToTenant($record) ? $record : null;
    }

    /**
     * @param  array<int, int|string>  $keys
     * @return Collection<int, RecordContract>
     */
    public function resolveRecords(array $keys): Collection
    {
        if ($this->blocked()) {
            return new Collection;
        }

        return $this->inner->resolveRecords($keys)
            ->filter(fn (RecordContract $record): bool => $this->belongsToTenant($record))
            ->values();
    }

    /**
     * Untouched: what a source can do does not depend on who is asking.
     */
    public function capabilities(): CapabilitySet
    {
        return $this->inner->capabilities();
    }

    public function changeToken(QueryPlan $plan): ?string
    {
        return $this->blocked() ? null : $this->inner->changeToken($this->scoped($plan));
    }

    /** Tenancy is on and nobody resolved — the one combination that shows nothing. */
    private function blocked(): bool
    {
        return $this->tenancy->shouldBlockEverything();
    }

    private function scoped(QueryPlan $plan): QueryPlan
    {
        $tenant = $this->tenancy->current();

        if ($tenant === null) {
            // Tenancy off. `blocked()` already answered the on-but-unresolved
            // case, so reaching here means there is nothing to scope by.
            return $plan;
        }

        return $plan->withFilters([new FilterClause($this->tenantColumn(), '=', $tenant)]);
    }

    private function belongsToTenant(RecordContract $record): bool
    {
        $tenant = $this->tenancy->current();

        // Loose comparison on purpose: a tenant key is an int in the database
        // and routinely a numeric string by the time it comes back from a
        // route, a session or a JSON API, and `1 !== '1'` would deny a tenant
        // its own rows.
        return $tenant === null || $record->get($this->tenantColumn()) == $tenant;
    }

    private function tenantColumn(): string
    {
        return $this->column ?? $this->tenancy->column();
    }
}
