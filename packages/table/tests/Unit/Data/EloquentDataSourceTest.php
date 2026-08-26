<?php

declare(strict_types=1);

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator as PaginatorContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Capabilities\Capability;
use NyonCode\WireCore\Core\Capabilities\CapabilitySet;
use NyonCode\WireCore\Core\Data\DataSource;
use NyonCode\WireCore\Core\Data\PagingRequest;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Exceptions\UnsupportedQueryAspectException;
use NyonCode\WireTable\Data\EloquentDataSource;

// ─── Fixture ─────────────────────────────────────────────────────────────────

class EdsRow extends Model
{
    protected $table = 'eds_rows';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::dropIfExists('eds_rows');
    Schema::create('eds_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });

    foreach (range(1, 7) as $i) {
        EdsRow::create(['name' => "row {$i}"]);
    }
});

function edsSource(): EloquentDataSource
{
    return new EloquentDataSource(EdsRow::query()->orderBy('id'));
}

// ─── Paging ──────────────────────────────────────────────────────────────────

it('pages length-aware, and knows the total', function () {
    $page = edsSource()->paginate(new QueryPlan, PagingRequest::lengthAware(3, 2));

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->total())->toBe(7)
        ->and($page->currentPage())->toBe(2)
        ->and($page->pluck('name')->all())->toBe(['row 4', 'row 5', 'row 6']);
});

it('pages simple, which asks for one row more instead of a count', function () {
    $page = edsSource()->paginate(new QueryPlan, PagingRequest::simple(3, 1));

    expect($page)->toBeInstanceOf(PaginatorContract::class)
        ->and($page)->not->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($page->hasMorePages())->toBeTrue()
        ->and($page->pluck('name')->all())->toBe(['row 1', 'row 2', 'row 3']);
});

it('pages by cursor', function () {
    $first = edsSource()->paginate(new QueryPlan, PagingRequest::cursor(3));

    expect($first)->toBeInstanceOf(CursorPaginator::class)
        ->and($first->pluck('name')->all())->toBe(['row 1', 'row 2', 'row 3']);

    $next = edsSource()->paginate(
        new QueryPlan,
        PagingRequest::cursor(3, $first->nextCursor()?->encode()),
    );

    expect($next->pluck('name')->all())->toBe(['row 4', 'row 5', 'row 6']);
});

it('honours a custom page name, so two tables page independently', function () {
    $page = edsSource()->paginate(new QueryPlan, PagingRequest::lengthAware(3, 3, 'rowsPage'));

    expect($page->pluck('name')->all())->toBe(['row 7'])
        ->and($page->url(1))->toContain('rowsPage=1');
});

it('turns the all-rows sentinel into one honest page', function () {
    // A negative limit is dropped by the query builder but still divides the
    // total in the paginator, so the sentinel must never reach it.
    $page = edsSource()->paginate(new QueryPlan, PagingRequest::lengthAware(-1));

    expect($page->lastPage())->toBe(1)
        ->and($page->total())->toBe(7)
        ->and($page->count())->toBe(7);
});

it('does not divide by zero when the sentinel meets an empty table', function () {
    EdsRow::query()->delete();

    $page = edsSource()->paginate(new QueryPlan, PagingRequest::lengthAware(-1));

    expect($page->total())->toBe(0)
        ->and($page->lastPage())->toBe(1);
});

// ─── get / count ─────────────────────────────────────────────────────────────

it('returns every row unpaginated', function () {
    expect(edsSource()->get(new QueryPlan))->toBeInstanceOf(Collection::class)->toHaveCount(7);
});

it('counts what the query matches', function () {
    expect((new EloquentDataSource(EdsRow::query()->where('name', 'row 3')))->count(new QueryPlan))->toBe(1)
        ->and(edsSource()->count(new QueryPlan))->toBe(7);
});

// ─── changeToken ─────────────────────────────────────────────────────────────

it('answers a change token that moves when the data does', function () {
    $before = edsSource()->changeToken(new QueryPlan);

    expect($before)->toBeString()->toStartWith('7:');

    EdsRow::create(['name' => 'row 8']);

    expect(edsSource()->changeToken(new QueryPlan))->not->toBe($before);
});

it('scopes the token to the query, not the table', function () {
    $narrow = new EloquentDataSource(EdsRow::query()->where('name', 'row 1'));

    expect($narrow->changeToken(new QueryPlan))->toStartWith('1:')
        ->and(edsSource()->changeToken(new QueryPlan))->toStartWith('7:');
});

// ─── Capabilities and degradation ────────────────────────────────────────────

it('declares everything an Eloquent builder can be asked for', function () {
    $caps = edsSource()->capabilities();

    foreach ([
        Capability::Searchable, Capability::Sortable, Capability::Filterable,
        Capability::Aggregateable, Capability::SqlExpression, Capability::Joinable,
        Capability::Paginable, Capability::SubRows, Capability::ChangeToken,
    ] as $capability) {
        expect($caps->has($capability))->toBeTrue();
    }
});

it('lets a source that cannot sort say so, and refuse loudly', function () {
    // The whole point of the contract. Written against a source that is not
    // Eloquent because a policy tested only against the source that can do
    // everything is a policy that has never run — and a table quietly ignoring
    // half its query is the failure this exists to prevent.
    $limited = new class implements DataSource
    {
        public function paginate(QueryPlan $plan, PagingRequest $paging): PaginatorContract
        {
            $this->guard($plan);

            throw new RuntimeException('unreachable in this test');
        }

        public function get(QueryPlan $plan): Collection
        {
            $this->guard($plan);

            return collect();
        }

        public function count(QueryPlan $plan): int
        {
            return 0;
        }

        public function capabilities(): CapabilitySet
        {
            return new CapabilitySet(Capability::Filterable, Capability::Paginable);
        }

        public function changeToken(QueryPlan $plan): ?string
        {
            return null;
        }

        private function guard(QueryPlan $plan): void
        {
            if ($plan->hasSorting() && ! $this->capabilities()->has(Capability::Sortable)) {
                throw UnsupportedQueryAspectException::notDeclared('sortable', self::class);
            }
        }
    };

    $unsorted = new QueryPlan;
    $sorted = new QueryPlan(sortClauses: [new SortClause('name')]);

    expect($limited->get($unsorted))->toHaveCount(0)
        ->and(fn () => $limited->get($sorted))
        ->toThrow(UnsupportedQueryAspectException::class, 'does not declare the [sortable] capability');
});

it('keeps the Eloquent source answering what the limited one refuses', function () {
    // Same plan, two sources, two outcomes — which is the degradation working
    // rather than the plan being malformed.
    $sorted = new QueryPlan(sortClauses: [new SortClause('name')]);

    expect(edsSource()->count($sorted))->toBe(7);
});
