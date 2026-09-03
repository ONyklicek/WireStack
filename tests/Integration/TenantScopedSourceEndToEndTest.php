<?php

declare(strict_types=1);

use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantScopedDataSource;
use NyonCode\WireTable\Data\CollectionDataSource;

/*
 * Tenancy over a real non-Eloquent source.
 *
 * The unit tests pin what the decorator hands the source. This pins the half
 * they cannot: that a real CollectionDataSource actually honours the filter it
 * is handed, on every method — because "the plan carries a tenant filter" is
 * worth nothing if the source ignores it, and that is exactly the gap this
 * decorator exists to close (v2-progress.md §4: "tenancy nad non-Eloquent
 * zdrojem nezapínej").
 *
 * The decorator lives in wire-core and CollectionDataSource in wire-table, so
 * this is the lowest place the two can meet.
 */

function tsIntegrationSource(int|string|null $tenant, bool $enabled = true): TenantScopedDataSource
{
    config()->set('wire-core.tenancy.enabled', $enabled);
    config()->set('wire-core.tenancy.column', 'tenant_id');

    $rows = [
        ['id' => 1, 'tenant_id' => 7, 'name' => 'ours-a'],
        ['id' => 2, 'tenant_id' => 9, 'name' => 'theirs'],
        ['id' => 3, 'tenant_id' => 7, 'name' => 'ours-b'],
    ];

    $tenancy = new Tenancy(new class($tenant) implements TenantResolver
    {
        public function __construct(private int|string|null $tenant) {}

        public function resolve(): int|string|null
        {
            return $this->tenant;
        }
    });

    return new TenantScopedDataSource(new CollectionDataSource($rows), $tenancy);
}

it('shows one tenant only its own rows, through every read', function () {
    $source = tsIntegrationSource(7);

    expect($source->get(new QueryPlan)->pluck('name')->all())->toBe(['ours-a', 'ours-b'])
        ->and($source->count(new QueryPlan))->toBe(2)
        ->and($source->paginate(new QueryPlan, PagingRequest::lengthAware(10))->total())->toBe(2);

    $chunked = [];
    $source->chunk(new QueryPlan, 10, function ($rows) use (&$chunked): void {
        $chunked = [...$chunked, ...collect($rows)->pluck('name')->all()];
    });

    expect($chunked)->toBe(['ours-a', 'ours-b']);
});

it('will not resolve another tenant-s record by its key', function () {
    // The oldest hole of this kind: a tenant types someone else's id into a URL.
    $source = tsIntegrationSource(7);

    expect($source->resolveRecord(1)?->get('name'))->toBe('ours-a')
        ->and($source->resolveRecord(2))->toBeNull()
        ->and($source->resolveRecords([1, 2, 3])->map(fn ($r) => $r->get('name'))->all())
        ->toBe(['ours-a', 'ours-b']);
});

it('shows nothing at all when tenancy is on and nobody resolved', function () {
    $source = tsIntegrationSource(null);

    expect($source->get(new QueryPlan))->toBeEmpty()
        ->and($source->count(new QueryPlan))->toBe(0)
        ->and($source->resolveRecord(1))->toBeNull();
});

it('leaves a single-tenant application exactly as it was', function () {
    $source = tsIntegrationSource(null, enabled: false);

    expect($source->count(new QueryPlan))->toBe(3)
        ->and($source->resolveRecord(2))->not->toBeNull();
});

it('narrows a plan that already had filters, rather than replacing them', function () {
    $source = tsIntegrationSource(7);
    $plan = (new QueryPlan)->withFilters([new FilterClause('name', '=', 'ours-b')]);

    expect($source->get($plan)->pluck('name')->all())->toBe(['ours-b']);
});
