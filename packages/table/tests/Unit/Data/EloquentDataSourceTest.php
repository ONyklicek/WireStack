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
use NyonCode\WireCore\Core\Data\RecordContract;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Exceptions\UnsupportedQueryAspectException;
use NyonCode\WireTable\Data\EloquentDataSource;
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;
use NyonCode\WireTable\Table;

// ─── Fixture ─────────────────────────────────────────────────────────────────

class EdsRow extends Model
{
    protected $table = 'eds_rows';

    protected $guarded = [];
}

class EdsStampless extends Model
{
    protected $table = 'eds_stampless';

    protected $guarded = [];

    public $timestamps = false;
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

    expect($before)->toBeString()->toStartWith('7|');

    EdsRow::create(['name' => 'row 8']);

    expect(edsSource()->changeToken(new QueryPlan))->not->toBe($before);
});

it('scopes the token to the query, not the table', function () {
    $narrow = new EloquentDataSource(EdsRow::query()->where('name', 'row 1'));

    expect($narrow->changeToken(new QueryPlan))->toStartWith('1|')
        ->and(edsSource()->changeToken(new QueryPlan))->toStartWith('7|');
});

// ─── Record resolution ───────────────────────────────────────────────────────

it('resolves one record by key, as a contract that unwraps to the model', function () {
    $record = edsSource()->resolveRecord(3);

    expect($record)->toBeInstanceOf(RecordContract::class)
        ->and($record->getKey())->toBe(3)
        ->and($record->get('name'))->toBe('row 3')
        ->and($record->unwrap())->toBeInstanceOf(EdsRow::class)
        ->and($record->toArray()['name'])->toBe('row 3');
});

it('answers null for a key the query does not match', function () {
    expect(edsSource()->resolveRecord(999))->toBeNull()
        // Scoped to the query, not the table: a key outside the narrowed set is
        // as absent as one that does not exist.
        ->and((new EloquentDataSource(EdsRow::query()->where('name', 'row 1')))->resolveRecord(2))->toBeNull();
});

it('resolves several records by key', function () {
    $records = edsSource()->resolveRecords([2, 4]);

    expect($records)->toHaveCount(2)
        ->and($records->map(fn (RecordContract $r) => $r->get('name'))->all())
        ->toBe(['row 2', 'row 4']);
});

it('answers an empty list for no keys, without asking the database', function () {
    // whereIn() with an empty list is `where 0 = 1` on some grammars and a
    // syntax error on others, so this branch is not an optimisation.
    expect(edsSource()->resolveRecords([]))->toHaveCount(0);
});

it('drops keys that do not match rather than failing on them', function () {
    expect(edsSource()->resolveRecords([1, 999])->map(fn (RecordContract $r) => $r->getKey())->all())
        ->toBe([1]);
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

        /**
         * @param  callable(Collection<int, mixed>): mixed  $callback
         */
        public function chunk(QueryPlan $plan, int $size, callable $callback): void
        {
            $this->guard($plan);
        }

        public function resolveRecord(int|string $key): ?RecordContract
        {
            return null;
        }

        /**
         * @param  array<int, int|string>  $keys
         * @return Collection<int, RecordContract>
         */
        public function resolveRecords(array $keys): Collection
        {
            return new Collection;
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

// ─── changeToken: the cases the extraction must not lose ─────────────────────

it('returns no token for a model that keeps no timestamps', function () {
    // WithTable::computePollChecksum() guards on this before touching the
    // column. Without the guard there is no updated_at to wrap and the query is
    // nonsense — so a source that cannot answer must say null, which is a real
    // answer meaning "compare rows yourself".
    Schema::dropIfExists('eds_stampless');
    Schema::create('eds_stampless', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    EdsStampless::create(['name' => 'a']);

    expect((new EloquentDataSource(EdsStampless::query()))->changeToken(new QueryPlan))->toBeNull();
});

it('qualifies the timestamp column, so a join cannot make it ambiguous', function () {
    // Two tables both carrying updated_at is the ordinary case once a column
    // sorts or filters through a relation.
    Schema::dropIfExists('eds_tags');
    Schema::create('eds_tags', function (Blueprint $t) {
        $t->id();
        $t->foreignId('eds_row_id');
        $t->timestamps();
    });

    $joined = EdsRow::query()->join('eds_tags', 'eds_tags.eds_row_id', '=', 'eds_rows.id');

    expect((new EloquentDataSource($joined))->changeToken(new QueryPlan))->toBeString();
});

it('ignores the ordering and any selected columns the query arrived with', function () {
    // An aggregate over an ordered, column-selected query is a different query.
    $shaped = EdsRow::query()->select(['name'])->orderByDesc('name');

    expect((new EloquentDataSource($shaped))->changeToken(new QueryPlan))->toStartWith('7|');
});

// ─── Table wiring ────────────────────────────────────────────────────────────

it('gives a table an Eloquent source it never asked for', function () {
    $table = Table::make()->model(EdsRow::class);

    expect($table->getDataSource())->toBeInstanceOf(EloquentDataSource::class)
        ->and($table->hasCustomDataSource())->toBeFalse()
        ->and($table->getDataSource()->count(new QueryPlan))->toBe(7);
});

it('memoises the default, so two asks are not two sources', function () {
    $table = Table::make()->model(EdsRow::class);

    expect($table->getDataSource())->toBe($table->getDataSource());
});

it('takes a source handed in, and says it was handed in', function () {
    $given = new EloquentDataSource(EdsRow::query()->where('name', 'row 1'));
    $table = Table::make()->model(EdsRow::class)->dataSource($given);

    expect($table->getDataSource())->toBe($given)
        ->and($table->hasCustomDataSource())->toBeTrue()
        ->and($table->getDataSource()->count(new QueryPlan))->toBe(1);
});

it('still refuses a table with no source at all', function () {
    expect(fn () => Table::make()->getDataSource())
        ->toThrow(TableHasNoDataSourceException::class);
});

it('lets a source stand in for a model entirely', function () {
    // The point of the opt-in: no model(), no query(), and the table still has
    // somewhere to read from.
    $table = Table::make()->dataSource(new EloquentDataSource(EdsRow::query()));

    expect($table->getDataSource()->count(new QueryPlan))->toBe(7)
        ->and($table->hasCustomDataSource())->toBeTrue();
});

it('streams in batches through chunkById, not by offset', function () {
    $batches = [];

    edsSource()->chunk(new QueryPlan, 3, function ($records) use (&$batches): void {
        $batches[] = $records->pluck('name')->all();
    });

    expect($batches)->toBe([
        ['row 1', 'row 2', 'row 3'],
        ['row 4', 'row 5', 'row 6'],
        ['row 7'],
    ]);
});

it('streams a joined query without an ambiguous cursor', function () {
    // chunkById defaults to an unqualified `id`, which a LEFT JOIN makes
    // ambiguous — the case an export hits whenever the table sorts by a
    // relation column. The source passes the qualified key for exactly this.
    Schema::dropIfExists('eds_notes');
    Schema::create('eds_notes', function (Blueprint $t) {
        $t->id();
        $t->foreignId('eds_row_id');
    });

    // Shaped the way the framework builds a joined query: ApplyRelations:35
    // selects `<table>.*`, without which the joined table's own `id` shadows the
    // parent's in the result and chunkById cannot find its cursor at all.
    $joined = EdsRow::query()
        ->select('eds_rows.*')
        ->leftJoin('eds_notes', 'eds_notes.eds_row_id', '=', 'eds_rows.id');
    $count = 0;

    (new EloquentDataSource($joined))->chunk(new QueryPlan, 2, function ($records) use (&$count): void {
        $count += $records->count();
    });

    expect($count)->toBe(7);
});
