<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Plugin\PluginManager;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireSortable\SortablePlugin;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

// ─── Test Models ─────────────────────────────────────────────────────────────

class TqsUser extends Model
{
    protected $table = 'tqs_users';

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(TqsCompany::class, 'company_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(TqsOrder::class, 'user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(TqsProfile::class, 'user_id');
    }
}

class TqsProfile extends Model
{
    protected $table = 'tqs_profiles';

    protected $guarded = [];
}

class TqsCompany extends Model
{
    protected $table = 'tqs_companies';

    protected $guarded = [];

    public function country(): BelongsTo
    {
        return $this->belongsTo(TqsCountry::class, 'country_id');
    }
}

class TqsCountry extends Model
{
    protected $table = 'tqs_countries';

    protected $guarded = [];

    public $timestamps = false;
}

class TqsOrder extends Model
{
    protected $table = 'tqs_orders';

    protected $guarded = [];
}

// ─── HasOneThrough fixture (Mechanic -> Car -> Owner) ─────────
// A HasOneThrough is neither morph nor to-many, so isJoinable() returns true —
// but the join builder cannot key it (it needs the intermediate table). It must
// stay out of the join and fall back safely.
class TqsMechanic extends Model
{
    protected $table = 'tqs_mechanics';

    protected $guarded = [];

    public $timestamps = false;

    public function ownerThrough(): HasOneThrough
    {
        return $this->hasOneThrough(TqsOwner::class, TqsCar::class, 'mechanic_id', 'car_id');
    }

    public function ownersThrough(): HasManyThrough
    {
        return $this->hasManyThrough(TqsOwner::class, TqsCar::class, 'mechanic_id', 'car_id');
    }
}

class TqsCar extends Model
{
    protected $table = 'tqs_cars';

    protected $guarded = [];

    public $timestamps = false;
}

class TqsOwner extends Model
{
    protected $table = 'tqs_owners';

    protected $guarded = [];

    public $timestamps = false;
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('tqs_countries', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('tqs_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('country_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tqs_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->integer('age')->default(0);
        $table->integer('position')->default(0);
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tqs_orders', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->integer('total');
        $table->timestamps();
    });

    Schema::create('tqs_profiles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id');
        $table->string('bio');
        $table->timestamps();
    });

    Schema::create('tqs_mechanics', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::create('tqs_cars', function (Blueprint $table) {
        $table->id();
        $table->foreignId('mechanic_id');
    });
    Schema::create('tqs_owners', function (Blueprint $table) {
        $table->id();
        $table->foreignId('car_id');
        $table->string('name');
    });

    TqsCountry::create(['id' => 1, 'name' => 'Wonderland']);

    TqsCompany::create(['id' => 1, 'name' => 'Acme Corp', 'country_id' => 1]);
    TqsCompany::create(['id' => 2, 'name' => 'Evil Corp', 'country_id' => 1]);

    TqsUser::create(['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com', 'age' => 30, 'position' => 2, 'company_id' => 1]);
    TqsUser::create(['id' => 2, 'name' => 'Bob', 'email' => 'bob@example.com', 'age' => 25, 'position' => 3, 'company_id' => 2]);
    TqsUser::create(['id' => 3, 'name' => 'Charlie', 'email' => 'charlie@example.com', 'age' => 35, 'position' => 1, 'company_id' => 1]);

    TqsOrder::create(['id' => 1, 'user_id' => 1, 'total' => 10]);
    TqsOrder::create(['id' => 2, 'user_id' => 1, 'total' => 20]);
    TqsOrder::create(['id' => 3, 'user_id' => 2, 'total' => 50]);

    // One profile per user (hasOne): bios sort Zulu > Mike > Alpha.
    TqsProfile::create(['user_id' => 1, 'bio' => 'Alpha']);   // Alice
    TqsProfile::create(['user_id' => 2, 'bio' => 'Zulu']);    // Bob
    TqsProfile::create(['user_id' => 3, 'bio' => 'Mike']);    // Charlie

    // HasOneThrough chain: mechanic -> car -> owner. Owner names sort
    // Anna < Mona < Zara, i.e. Mo < Jack < Manny. The id ranges are deliberately
    // disjoint (cars.id != cars.mechanic_id, owners.car_id != owners.id) so that
    // mis-keyed joins produce a different result instead of coincidentally matching.
    TqsMechanic::insert([
        ['id' => 1, 'name' => 'Manny'],
        ['id' => 2, 'name' => 'Mo'],
        ['id' => 3, 'name' => 'Jack'],
    ]);
    TqsCar::insert([
        ['id' => 101, 'mechanic_id' => 1],
        ['id' => 102, 'mechanic_id' => 2],
        ['id' => 103, 'mechanic_id' => 3],
    ]);
    TqsOwner::insert([
        ['id' => 201, 'car_id' => 101, 'name' => 'Zara'],   // Manny
        ['id' => 202, 'car_id' => 102, 'name' => 'Anna'],   // Mo
        ['id' => 203, 'car_id' => 103, 'name' => 'Mona'],   // Jack
    ]);
});

afterEach(function () {
    Schema::dropIfExists('tqs_owners');
    Schema::dropIfExists('tqs_cars');
    Schema::dropIfExists('tqs_mechanics');
    Schema::dropIfExists('tqs_profiles');
    Schema::dropIfExists('tqs_orders');
    Schema::dropIfExists('tqs_users');
    Schema::dropIfExists('tqs_companies');
    Schema::dropIfExists('tqs_countries');
});

/**
 * Identifier-quote-agnostic SQL. SQLite and Postgres quote identifiers with `"`,
 * MySQL/MariaDB with backticks; stripping both lets the join/where assertions
 * hold on every database the CI matrix runs.
 */
function tqsUnquoted(string $sql): string
{
    return str_replace(['`', '"'], '', $sql);
}

// ─── Basic Query Building ────────────────────────────────────────────────────

it('builds a basic query without search/filter/sort', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
            Column::make('email'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
    );

    $results = $query->get();
    expect($results)->toHaveCount(3);
});

it('produces a QueryPlan after building', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable(),
            Column::make('email'),
        ]);

    $service = new TableQueryService;
    $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'Alice',
    );

    $plan = $service->getLastPlan();
    expect($plan)->not->toBeNull()
        ->and($plan->hasSearch())->toBeTrue()
        ->and($plan->searchClauses)->toHaveCount(1);
});

// ─── Search ──────────────────────────────────────────────────────────────────

it('applies search to searchable columns', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable(),
            Column::make('email')->searchable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'alice',
    );

    $results = $query->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Alice');
});

it('handles custom search callbacks', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable(query: function ($query, $search) {
                $query->where('name', 'like', "%{$search}%");
            }),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'Bob',
    );

    $results = $query->get();
    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Bob');
});

// ─── Column-level filters honour the column operator ─────────────────────────

it('applies column filters through the column operator (default like = partial match)', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')->filterable()]);

    $names = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        columnFilterValues: ['name' => 'li'],
    )->get()->pluck('name')->all();

    expect($names)->toContain('Alice', 'Charlie')
        ->and($names)->not->toContain('Bob');
});

it('honours a custom column filterOperator for exact match', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')->filterable()->filterOperator('=')]);

    $service = new TableQueryService;

    // Exact match: a partial term matches nothing.
    expect($service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        columnFilterValues: ['name' => 'li'],
    )->get())->toHaveCount(0);

    // The full value matches exactly one row.
    expect($service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        columnFilterValues: ['name' => 'Alice'],
    )->get())->toHaveCount(1);
});

// ─── Sorting ─────────────────────────────────────────────────────────────────

it('applies sorting', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->sortable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'desc',
    );

    $results = $query->get();
    expect($results->first()->name)->toBe('Charlie')
        ->and($results->last()->name)->toBe('Alice');
});

it('handles custom sort callbacks', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('age')->sortable(query: function ($query, $direction) {
                return $query->orderBy('age', $direction);
            }),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'age',
        sortDirection: 'asc',
    );

    $results = $query->get();
    expect($results->first()->age)->toBe(25)
        ->and($results->last()->age)->toBe(35);
});

// ─── Filters ─────────────────────────────────────────────────────────────────

it('applies basic filter', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
        ])
        ->filters([
            SelectFilter::make('company_id')
                ->options(['1' => 'Acme', '2' => 'Evil']),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company_id' => ['value' => 1]],
    );

    $results = $query->get();
    expect($results)->toHaveCount(2); // Alice and Charlie
});

it('handles custom filter query callbacks', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
        ])
        ->filters([
            Filter::make('age_min')
                ->query(fn ($query, $value) => $query->where('age', '>=', $value)),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['age_min' => ['value' => 30]],
    );

    $results = $query->get();
    expect($results)->toHaveCount(2); // Alice (30) and Charlie (35)
});

// ─── Relation Columns ────────────────────────────────────────────────────────

it('handles relation columns with eager loading', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name'),
            Column::make('company.name'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
    );

    $plan = $service->getLastPlan();
    expect($plan)->not->toBeNull();

    // Should have either joins or eager loads for the company relation
    $hasJoinsOrEagerLoads = $plan->hasJoins() || $plan->hasEagerLoads();
    expect($hasJoinsOrEagerLoads)->toBeTrue();
});

// ─── Aggregate Columns ──────────────────────────────────────────────────────

it('applies aggregate columns to the built query', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('orders_count')->counts('orders'),
            Column::make('orders_sum_total')->sums('orders', 'total'),
            Column::make('orders_avg_total')->averages('orders', 'total'),
            Column::make('orders_min_total')->mins('orders', 'total'),
            Column::make('orders_max_total')->maxes('orders', 'total'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
    );

    $alice = $query->where('name', 'Alice')->firstOrFail();

    expect((int) $alice->orders_count)->toBe(2)
        ->and((float) $alice->orders_sum_total)->toBe(30.0)
        ->and((float) $alice->orders_avg_total)->toBe(15.0)
        ->and((float) $alice->orders_min_total)->toBe(10.0)
        ->and((float) $alice->orders_max_total)->toBe(20.0);
});

// ─── Combined Operations ─────────────────────────────────────────────────────

it('handles search + filter + sort together', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->searchable()->sortable(),
            Column::make('email')->searchable(),
        ])
        ->filters([
            Filter::make('company_id'),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'example.com',
        filterValues: ['company_id' => ['value' => 1]],
        sortColumn: 'name',
        sortDirection: 'asc',
    );

    $results = $query->get();
    // Both Alice and Charlie are in company 1 and have example.com emails
    expect($results)->toHaveCount(2)
        ->and($results->first()->name)->toBe('Alice')
        ->and($results->last()->name)->toBe('Charlie');
});

// ─── Empty Filters Ignored ───────────────────────────────────────────────────

it('ignores empty filter values', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')])
        ->filters([Filter::make('company_id')]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company_id' => null],
    );

    $results = $query->get();
    expect($results)->toHaveCount(3); // All users
});

it('ignores sort for non-sortable columns', function () {
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'desc',
    );

    // Should still work, just no ordering
    $results = $query->get();
    expect($results)->toHaveCount(3);
});

// ─── Plugin Runtime Wiring ──────────────────────────────────────────────────

it('lets table.configuring hooks modify columns before planning', function () {
    $manager = app(PluginManager::class);
    $manager->hook('table.configuring', function (array $payload) {
        $payload['columns'] = [
            Column::make('name')->searchable(),
        ];

        return $payload;
    });

    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        search: 'Alice',
    );

    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Alice')
        ->and($service->getLastPlan()?->hasSearch())->toBeTrue();
});

it('lets table.querying hooks force sort before the query is planned', function () {
    $manager = app(PluginManager::class);
    $manager->hook('table.querying', function (array $payload) {
        $payload['force_sort_column'] = 'position';
        $payload['force_sort_direction'] = 'asc';

        return $payload;
    });

    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->sortable(),
            Column::make('position')->sortable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'asc',
    );

    $results = $query->get();

    expect($results->pluck('name')->all())->toBe(['Charlie', 'Alice', 'Bob']);
});

it('runs plugin query pipes after the default query executor pipes', function () {
    $manager = app(PluginManager::class);
    $manager->addQueryPipe('adult-filter', new class implements QueryPipe
    {
        public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
        {
            $builder = $next($builder, $plan);

            return $builder->where('age', '>=', 30);
        }
    });

    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->sortable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'asc',
    );

    expect($query->pluck('name')->all())->toBe(['Alice', 'Charlie']);
});

it('dispatches table.queried hooks with the built query and plan', function () {
    $manager = app(PluginManager::class);
    $observed = [];

    $manager->hook('table.queried', function (array $payload) use (&$observed) {
        $observed['query'] = $payload['query'];
        $observed['plan'] = $payload['plan'];

        return $payload;
    });

    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([
            Column::make('name')->sortable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
    );

    expect($observed['query'])->toBe($query)
        ->and($observed['plan'])->toBe($service->getLastPlan());
});

it('uses SortablePlugin force sort overrides while a table is reordering', function () {
    $manager = app(PluginManager::class);
    $manager->register(new SortablePlugin);

    $component = new class
    {
        public function isTableReordering(): bool
        {
            return true;
        }
    };

    $table = SortableTable::make()
        ->model(TqsUser::class)
        ->reorderable('position')
        ->livewireComponent($component)
        ->columns([
            Column::make('name')->sortable(),
            Column::make('position')->sortable(),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'name',
        sortDirection: 'asc',
    );

    expect($query->pluck('name')->all())->toBe(['Charlie', 'Alice', 'Bob']);
});

// ─── Edge cases: guards and relation-path resolution ─────────

it('ignores a sort request for a column that is not in the table', function () {
    // findColumn() returns null, so the sort is dropped rather than sorting by
    // a name that does not exist.
    $table = Table::make()->model(TqsUser::class)->columns([Column::make('name')]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'not_a_column',
    );

    // Falls back to the default (unsorted) order — all rows, no error.
    expect($query->get())->toHaveCount(3);
});

it('skips a filter the viewer is not allowed to see even when it has a value', function () {
    // canView() is false for a hidden filter, so its active value is ignored
    // rather than silently applied.
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name')])
        ->filters([
            SelectFilter::make('company_id')
                ->options([1 => 'Acme', 2 => 'Evil'])
                ->visible(false),
        ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company_id' => 1],
    );

    // The hidden filter did not constrain the result.
    expect($query->get())->toHaveCount(3);
});

it('resolves a related columns metadata by walking the relation chain', function () {
    // company.name walks user -> company, lazily registering the related model
    // the initial scan did not include.
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name'), Column::make('company.name')]);

    $service = new TableQueryService;
    $service->buildQuery(baseQuery: TqsUser::query(), table: $table);

    // The related model was registered on demand while resolving the column.
    expect($service->getLastRegistry()?->hasModel(TqsCompany::class))->toBeTrue();
});

it('leaves an aggregate column for the planner and does not auto-detect it', function () {
    // "orders->count()" has no DB column to inspect, so metadata resolution
    // returns nothing and the build proceeds without error.
    $table = Table::make()
        ->model(TqsUser::class)
        ->columns([Column::make('name'), Column::make('orders->count()')]);

    $query = (new TableQueryService)->buildQuery(baseQuery: TqsUser::query(), table: $table);

    expect($query->get())->toHaveCount(3);
});

// ─── belongsTo relation-column sorting (LEFT JOIN) ────────────
//
// The docs promise "Simple belongsTo relations -> LEFT JOIN (enables sort)".
// This is the end-to-end test that was missing: before the relation metadata
// was wired into registerRelationChain, sorting by a belongsTo column silently
// produced `select * from users` (no join, unsorted). These lock the behaviour.

it('sorts by a belongsTo relation column via a LEFT JOIN', function () {
    $table = Table::make()->model(TqsUser::class)->columns([
        Column::make('name'),
        Column::make('company.name')->sortable(),
    ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'company.name',
        sortDirection: 'asc',
    );

    // A real LEFT JOIN on the related table, ordered by the joined column.
    expect(tqsUnquoted($query->toSql()))
        ->toContain('left join tqs_companies')
        ->toContain('order by')
        // The base select is qualified so `name` is unambiguous.
        ->toContain('tqs_users.*');

    // Alice + Charlie (Acme) sort before Bob (Evil). The Acme pair ties on the
    // company name, so assert the group, not a DB-specific intra-tie order.
    $names = $query->get()->pluck('name')->all();
    expect($names[2])->toBe('Bob')
        ->and([$names[0], $names[1]])->toContain('Alice')
        ->and([$names[0], $names[1]])->toContain('Charlie');
});

it('does not corrupt a base column that shares a name with the joined table', function () {
    // Regression guard: users.name and companies.name collide under `select *`.
    // The qualified base select must keep the user's own name in the cell.
    $table = Table::make()->model(TqsUser::class)->columns([
        Column::make('name'),
        Column::make('company.name')->sortable(),
    ]);

    $rows = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'company.name',
    )->get();

    // `name` is the user's own name, not the joined company name. Bob (Evil)
    // sorts last; the Acme pair leads in a DB-defined tie order.
    $names = $rows->pluck('name')->all();
    expect($names[2])->toBe('Bob')
        ->and([$names[0], $names[1]])->toContain('Alice')
        ->and([$names[0], $names[1]])->toContain('Charlie')
        // …and the related value is still reachable for display via eager load.
        ->and($rows->first()->company->name)->toBe('Acme Corp');
});

it('sorts descending by a belongsTo column', function () {
    $table = Table::make()->model(TqsUser::class)->columns([
        Column::make('name'),
        Column::make('company.name')->sortable(),
    ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'company.name',
        sortDirection: 'desc',
    );

    // Evil before Acme → Bob first.
    expect($query->get()->pluck('name')->first())->toBe('Bob');
});

it('leaves a plain query untouched when no relation column is sorted', function () {
    // The join qualification must only trigger when a join is actually planned.
    $table = Table::make()->model(TqsUser::class)->columns([Column::make('name')]);

    $sql = (new TableQueryService)->buildQuery(baseQuery: TqsUser::query(), table: $table)->toSql();

    expect(tqsUnquoted($sql))->not->toContain('left join')
        ->and(tqsUnquoted($sql))->not->toContain('tqs_users.*');
});

it('sorts by a hasOne relation column via a LEFT JOIN', function () {
    // hasOne is the other singular joinable relation: the builder keys it as
    // parent.id = related.foreign_key. Bios sort Alpha < Mike < Zulu, i.e.
    // Alice < Charlie < Bob.
    $table = Table::make()->model(TqsUser::class)->columns([
        Column::make('name'),
        Column::make('profile.bio')->sortable(),
    ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'profile.bio',
        sortDirection: 'asc',
    );

    expect(tqsUnquoted($query->toSql()))
        ->toContain('left join tqs_profiles')
        ->toContain('tqs_users.id')       // hasOne joins on the parent key…
        ->toContain('tqs_users.*')
        ->toContain('order by');

    // Bios sort Alpha < Mike < Zulu (unique keys), so the order is deterministic.
    expect($query->get()->pluck('name')->all())->toBe(['Alice', 'Charlie', 'Bob']);
});

it('filters by a hasOne relation column via the LEFT JOIN', function () {
    $table = Table::make()->model(TqsUser::class)
        ->columns([Column::make('name'), Column::make('profile.bio')])
        ->filters([
            SelectFilter::make('profile.bio')->options(['Zulu' => 'Zulu', 'Alpha' => 'Alpha']),
        ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['profile' => ['bio' => 'Zulu']],
    );

    expect(tqsUnquoted($query->toSql()))->toContain('left join tqs_profiles');
    // Only Bob has the Zulu bio.
    expect($query->get()->pluck('name')->all())->toBe(['Bob']);
});

// ─── belongsTo relation-column filtering (LEFT JOIN) ──────────
//
// The same LEFT JOIN that powers relation sorting also powers relation
// filtering: planFilter() calls the same registerRelationJoins(), so a filter
// on a belongsTo column constrains the joined table in SQL — no whereHas
// subquery, no in-memory pass. The filter takes a flat name (its state key)
// and carries the relation path on column().

it('filters by a belongsTo relation column through the LEFT JOIN', function () {
    $table = Table::make()->model(TqsUser::class)
        ->columns([Column::make('name'), Column::make('company.name')])
        ->filters([
            SelectFilter::make('company')->column('company.name')
                ->options(['Acme Corp' => 'Acme', 'Evil Corp' => 'Evil']),
        ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company' => 'Acme Corp'],
    );

    // Constrained in SQL on the joined table, base select still qualified.
    expect(tqsUnquoted($query->toSql()))
        ->toContain('left join tqs_companies')
        ->toContain('tqs_users.*')
        ->toContain('where');

    // Only Acme users (Alice, Charlie) survive; Bob (Evil) is filtered out.
    expect($query->get()->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Charlie']);
});

it('reuses a single join when sorting and filtering the same relation', function () {
    // registerRelationJoins keys the alias off (baseTable, path), so the sort
    // and the filter share one join rather than emitting two.
    $table = Table::make()->model(TqsUser::class)
        ->columns([Column::make('name'), Column::make('company.name')->sortable()])
        ->filters([
            SelectFilter::make('company')->column('company.name')
                ->options(['Acme Corp' => 'Acme', 'Evil Corp' => 'Evil']),
        ]);

    $sql = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company' => 'Acme Corp'],
        sortColumn: 'company.name',
        sortDirection: 'asc',
    )->toSql();

    // Exactly one join, carrying both the where and the order by.
    expect(substr_count($sql, 'left join'))->toBe(1);
    expect($sql)->toContain('where')->toContain('order by');
});

it('filters by a belongsTo column through the column header filter too', function () {
    // The column-header filter path (Column::filterable()) routes through the
    // same FilterDefinition::make() -> relation join as the table filter.
    $table = Table::make()->model(TqsUser::class)
        ->columns([
            Column::make('name'),
            Column::make('company.name')->filterable()->filterOperator('='),
        ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        columnFilterValues: ['company.name' => 'Evil Corp'],
    );

    expect(tqsUnquoted($query->toSql()))->toContain('left join tqs_companies');
    expect($query->get()->pluck('name')->all())->toBe(['Bob']);
});

it('accepts the relation path directly in the filter name (dot notation)', function () {
    // SelectFilter::make('company.name') needs no ->column(): the UI binds
    // wire:model="tableState.filters.company.name", so the value arrives as
    // nested state and data_get(getName()) reads it back the same way.
    $table = Table::make()->model(TqsUser::class)
        ->columns([Column::make('name'), Column::make('company.name')])
        ->filters([
            SelectFilter::make('company.name')
                ->options(['Acme Corp' => 'Acme', 'Evil Corp' => 'Evil']),
        ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        filterValues: ['company' => ['name' => 'Acme Corp']],
    );

    expect(tqsUnquoted($query->toSql()))->toContain('left join tqs_companies');
    expect($query->get()->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Charlie']);
});

it('sorts through a nested belongsTo chain (user -> company -> country)', function () {
    // Each belongsTo segment in the chain is registered and joined in turn, so a
    // two-level path resolves to two chained LEFT JOINs ending on the leaf column.
    $table = Table::make()->model(TqsUser::class)->columns([
        Column::make('name'),
        Column::make('company.country.name')->sortable(),
    ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsUser::query(),
        table: $table,
        sortColumn: 'company.country.name',
        sortDirection: 'asc',
    );

    // Two chained joins, base select qualified, ordered by the leaf column.
    expect(substr_count(tqsUnquoted($query->toSql()), 'left join'))->toBe(2);
    expect(tqsUnquoted($query->toSql()))
        ->toContain('left join tqs_companies')
        ->toContain('left join tqs_countries')
        ->toContain('tqs_users.*');

    // All three users resolve (every company shares Wonderland); no error.
    expect($query->get())->toHaveCount(3);
});

it('sorts through a hasOneThrough via two chained joins (base -> intermediate -> far)', function () {
    // HasOneThrough reaches the far table through an intermediate one, so it
    // needs TWO joins: base -> intermediate (through.first = base.local) and
    // intermediate -> far (far.foreign = through.secondLocal). The intermediate
    // gets its own synthetic alias.
    $table = Table::make()->model(TqsMechanic::class)->columns([
        Column::make('name'),
        Column::make('ownerThrough.name')->sortable(),
    ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsMechanic::query(),
        table: $table,
        sortColumn: 'ownerThrough.name',
        sortDirection: 'asc',
    );

    // Two joins: the intermediate cars table and the far owners table.
    expect(substr_count(tqsUnquoted($query->toSql()), 'left join'))->toBe(2);
    expect(tqsUnquoted($query->toSql()))
        ->toContain('left join tqs_cars')
        ->toContain('left join tqs_owners')
        ->toContain('tqs_mechanics.*');

    // Owners sort Anna < Mona < Zara (unique keys) → Mo, Jack, Manny.
    expect($query->get()->pluck('name')->all())->toBe(['Mo', 'Jack', 'Manny']);
});

it('filters through a hasOneThrough via the same chained joins', function () {
    $table = Table::make()->model(TqsMechanic::class)
        ->columns([Column::make('name'), Column::make('ownerThrough.name')])
        ->filters([
            SelectFilter::make('ownerThrough.name')->options(['Zara' => 'Zara', 'Anna' => 'Anna']),
        ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: TqsMechanic::query(),
        table: $table,
        filterValues: ['ownerThrough' => ['name' => 'Zara']],
    );

    expect(substr_count($query->toSql(), 'left join'))->toBe(2);
    // Only Manny's owner is Zara.
    expect($query->get()->pluck('name')->all())->toBe(['Manny']);
});

it('reuses the intermediate + far joins when sorting and filtering one hasOneThrough', function () {
    // Both the intermediate and far joins are keyed off deterministic aliases, so
    // sort + filter on the same through relation still emit exactly two joins.
    $table = Table::make()->model(TqsMechanic::class)
        ->columns([Column::make('name'), Column::make('ownerThrough.name')->sortable()])
        ->filters([
            SelectFilter::make('ownerThrough.name')->options(['Zara' => 'Zara', 'Anna' => 'Anna']),
        ]);

    $sql = (new TableQueryService)->buildQuery(
        baseQuery: TqsMechanic::query(),
        table: $table,
        filterValues: ['ownerThrough' => ['name' => 'Anna']],
        sortColumn: 'ownerThrough.name',
        sortDirection: 'asc',
    )->toSql();

    expect(substr_count($sql, 'left join'))->toBe(2);
    expect($sql)->toContain('where')->toContain('order by');
});

it('does not join a HasManyThrough column (to-many stays display-only)', function () {
    // The many variant is to-many: a join would multiply parent rows, so it is
    // excluded (isToMany → isJoinable() false, and it is not in the whitelist).
    $table = Table::make()->model(TqsMechanic::class)->columns([
        Column::make('name'),
        Column::make('ownersThrough.name')->sortable(),
    ]);

    $sql = (new TableQueryService)->buildQuery(
        baseQuery: TqsMechanic::query(),
        table: $table,
        sortColumn: 'ownersThrough.name',
        sortDirection: 'asc',
    )->toSql();

    expect(tqsUnquoted($sql))->not->toContain('left join')
        ->and(tqsUnquoted($sql))->not->toContain('tqs_mechanics.*');
});
