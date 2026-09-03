<?php

declare(strict_types=1);

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Data\ArrayRecord;
use NyonCode\WireCore\Core\Data\DataSource;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Tenancy\Contracts\TenantResolver;
use NyonCode\WireCore\Core\Tenancy\Tenancy;
use NyonCode\WireCore\Core\Tenancy\TenantScopedDataSource;

/*
 * Tenancy for a source Eloquent's global scope cannot reach.
 *
 * The property under test is not "it filters" — it is *where* it filters and
 * what happens when it cannot. Scoping happens by adding a filter to the plan,
 * so a source that cannot honour it refuses out loud instead of returning rows
 * nobody constrained; and tenancy on with no tenant answers with nothing.
 */

/** Records what it was asked, and answers whatever it was told to. */
final class TsRecordingSource implements DataSource
{
    public ?QueryPlan $lastPlan = null;

    public int $calls = 0;

    /** @param array<int, array<string, mixed>> $rows */
    public function __construct(private array $rows = []) {}

    public function paginate(QueryPlan $plan, PagingRequest $paging): Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $this->lastPlan = $plan;
        $this->calls++;

        return new LengthAwarePaginator($this->rows, count($this->rows), 10, 1);
    }

    public function get(QueryPlan $plan): Collection
    {
        $this->lastPlan = $plan;
        $this->calls++;

        return new Collection($this->rows);
    }

    public function chunk(QueryPlan $plan, int $size, callable $callback): void
    {
        $this->lastPlan = $plan;
        $this->calls++;
    }

    public function count(QueryPlan $plan): int
    {
        $this->lastPlan = $plan;
        $this->calls++;

        return count($this->rows);
    }

    public function resolveRecord(int|string $key): ?RecordContract
    {
        $this->calls++;
        $row = collect($this->rows)->firstWhere('id', $key);

        return $row === null ? null : new ArrayRecord($row, 'id');
    }

    public function resolveRecords(array $keys): Collection
    {
        $this->calls++;

        return collect($this->rows)
            ->whereIn('id', $keys)
            ->map(fn (array $row): RecordContract => new ArrayRecord($row, 'id'))
            ->values();
    }

    public function capabilities(): CapabilitySet
    {
        return new CapabilitySet;
    }

    public function changeToken(QueryPlan $plan): ?string
    {
        $this->lastPlan = $plan;

        return 'token';
    }
}

function tsTenancy(int|string|null $tenant, bool $enabled = true): Tenancy
{
    config()->set('wire-core.tenancy.enabled', $enabled);
    config()->set('wire-core.tenancy.column', 'tenant_id');

    return new Tenancy(new class($tenant) implements TenantResolver
    {
        public function __construct(private int|string|null $tenant) {}

        public function resolve(): int|string|null
        {
            return $this->tenant;
        }
    });
}

function tsRows(): array
{
    return [
        ['id' => 1, 'tenant_id' => 7, 'name' => 'ours'],
        ['id' => 2, 'tenant_id' => 9, 'name' => 'theirs'],
    ];
}

it('constrains the plan rather than the rows it gets back', function () {
    // The decision this class is built on: every source already applies
    // QueryPlan::$filters, and one that cannot apply this filter refuses out
    // loud. Filtering the returned rows instead would be right for get() and
    // quietly wrong for count() and paginate(), which never hand rows over.
    $inner = new TsRecordingSource(tsRows());
    $source = new TenantScopedDataSource($inner, tsTenancy(7));

    $source->get(new QueryPlan);

    expect($inner->lastPlan?->filters)->toHaveCount(1)
        ->and($inner->lastPlan?->filters[0]->column)->toBe('tenant_id')
        ->and($inner->lastPlan?->filters[0]->operator)->toBe('=')
        ->and($inner->lastPlan?->filters[0]->value)->toBe(7);
});

it('keeps the filters the plan already carried', function () {
    // Narrows, never widens: a decorator that replaced the filters would drop
    // the user's own and show them more than they asked for.
    $inner = new TsRecordingSource;
    $source = new TenantScopedDataSource($inner, tsTenancy(7));

    $source->count((new QueryPlan)->withFilters([new FilterClause('status', '=', 'open')]));

    expect($inner->lastPlan?->filters)->toHaveCount(2)
        ->and(array_column(array_map(fn ($f) => (array) $f, $inner->lastPlan?->filters ?? []), 'column'))
        ->toBe(['status', 'tenant_id']);
});

it('answers with nothing when tenancy is on and no tenant resolved', function () {
    // The same fail-safe TenantScope has, and the reason this is a class rather
    // than a note in the docs: before login, on a worker, in a console command,
    // the answer must be nothing rather than everything.
    $inner = new TsRecordingSource(tsRows());
    $source = new TenantScopedDataSource($inner, tsTenancy(null));

    expect($source->get(new QueryPlan))->toBeEmpty()
        ->and($source->count(new QueryPlan))->toBe(0)
        ->and($source->paginate(new QueryPlan, PagingRequest::lengthAware(10))->total())->toBe(0)
        ->and($source->resolveRecord(1))->toBeNull()
        ->and($source->resolveRecords([1, 2]))->toBeEmpty()
        ->and($source->changeToken(new QueryPlan))->toBeNull()
        // Nothing reached the inner source at all: blocked means not asked.
        ->and($inner->calls)->toBe(0);
});

it('does not run a chunk callback for a tenant-less request', function () {
    $inner = new TsRecordingSource(tsRows());
    $source = new TenantScopedDataSource($inner, tsTenancy(null));

    $source->chunk(new QueryPlan, 10, fn () => throw new RuntimeException('must not run'));

    expect($inner->calls)->toBe(0);
});

it('refuses a record belonging to another tenant', function () {
    // Resolution takes a key, not a plan, so the filter cannot be added to
    // anything — the record is fetched and checked. Without this a tenant
    // reaches another tenant's row by typing its id into a URL.
    $source = new TenantScopedDataSource(new TsRecordingSource(tsRows()), tsTenancy(7));

    expect($source->resolveRecord(1)?->getKey())->toBe(1)
        ->and($source->resolveRecord(2))->toBeNull()
        ->and($source->resolveRecords([1, 2])->map(fn (RecordContract $r) => $r->getKey())->all())->toBe([1]);
});

it('matches a tenant key across the types a request turns it into', function () {
    // An int in the database, a numeric string out of a route or a session.
    $source = new TenantScopedDataSource(new TsRecordingSource(tsRows()), tsTenancy('7'));

    expect($source->resolveRecord(1))->not->toBeNull();
});

it('delegates untouched when tenancy is off', function () {
    // Wrapping a source must cost nothing in the single-tenant application that
    // most of them are.
    $inner = new TsRecordingSource(tsRows());
    $source = new TenantScopedDataSource($inner, tsTenancy(null, enabled: false));

    expect($source->get(new QueryPlan))->toHaveCount(2)
        ->and($inner->lastPlan?->filters)->toBe([])
        ->and($source->resolveRecord(2))->not->toBeNull();
});

it('leaves capabilities to the source it wraps', function () {
    // What a source can do does not depend on who is asking.
    $inner = new TsRecordingSource;

    expect((new TenantScopedDataSource($inner, tsTenancy(7)))->capabilities())
        ->toEqual($inner->capabilities());
});

it('takes a column of its own when the source names one', function () {
    $inner = new TsRecordingSource;
    $source = new TenantScopedDataSource($inner, tsTenancy(7), 'organisation_id');

    $source->get(new QueryPlan);

    expect($inner->lastPlan?->filters[0]->column)->toBe('organisation_id');
});
