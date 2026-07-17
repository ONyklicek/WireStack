<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

/*
 * A joined relation is constrained by the joined model's global scopes — not
 * just SoftDeletes, but any registered scope. The related model is joined as a
 * scoped subquery (`Model::query()`), so its scopes filter the joined rows the
 * same way Eloquent's own relation query would, while the LEFT JOIN stays LEFT
 * (a parent whose related rows are all scoped away keeps NULLs, is not dropped).
 * A model with no global scopes keeps a plain direct-table join.
 */

class ScActiveScope implements Scope
{
    public function apply(EloquentBuilder $builder, Model $model): void
    {
        $builder->where($model->getTable().'.active', true);
    }
}

class ScUser extends Model
{
    protected $table = 'sc_users';

    protected $guarded = [];

    public $timestamps = false;

    public function company(): BelongsTo
    {
        return $this->belongsTo(ScCompany::class, 'company_id');
    }

    public function plainCompany(): BelongsTo
    {
        return $this->belongsTo(ScPlainCompany::class, 'plain_company_id');
    }

    // Constraint declared on the relation method, against a model with NO global
    // scopes — the join must still honour the `->where('active', true)`.
    public function activePlainCompany(): BelongsTo
    {
        return $this->belongsTo(ScPlainCompany::class, 'plain_company_id')
            ->where('sc_plain_companies.active', true);
    }
}

/** Soft-deletes AND a custom "active" global scope — both must apply. */
class ScCompany extends Model
{
    use SoftDeletes;

    protected $table = 'sc_companies';

    protected $guarded = [];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope(new ScActiveScope);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(ScRegion::class, 'region_id');
    }
}

class ScRegion extends Model
{
    protected $table = 'sc_regions';

    protected $guarded = [];

    public $timestamps = false;
}

class ScPlainCompany extends Model
{
    protected $table = 'sc_plain_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class ScMechanic extends Model
{
    protected $table = 'sc_mechanics';

    protected $guarded = [];

    public $timestamps = false;

    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(ScOwner::class, ScCar::class, 'mechanic_id', 'car_id');
    }
}

class ScCar extends Model
{
    use SoftDeletes;

    protected $table = 'sc_cars';

    protected $guarded = [];

    public $timestamps = false;
}

class ScOwner extends Model
{
    use SoftDeletes;

    protected $table = 'sc_owners';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('sc_regions', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('sc_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->boolean('active')->default(true);
        $t->foreignId('region_id')->nullable();
        $t->softDeletes();
    });
    Schema::create('sc_plain_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->boolean('active')->default(true);
    });
    Schema::create('sc_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->foreignId('company_id')->nullable();
        $t->foreignId('plain_company_id')->nullable();
    });
    Schema::create('sc_mechanics', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('sc_cars', function (Blueprint $t) {
        $t->id();
        $t->foreignId('mechanic_id');
        $t->softDeletes();
    });
    Schema::create('sc_owners', function (Blueprint $t) {
        $t->id();
        $t->foreignId('car_id');
        $t->string('name');
        $t->softDeletes();
    });

    ScRegion::insert([['id' => 1, 'name' => 'North'], ['id' => 2, 'name' => 'South']]);
    ScCompany::withoutGlobalScopes()->insert([
        ['id' => 1, 'name' => 'Acme', 'active' => true, 'region_id' => 1, 'deleted_at' => null],
        ['id' => 2, 'name' => 'Ghost', 'active' => true, 'region_id' => 2, 'deleted_at' => '2020-01-01 00:00:00'], // trashed
        ['id' => 3, 'name' => 'Sleepy', 'active' => false, 'region_id' => 2, 'deleted_at' => null],                // inactive
    ]);
    // Plain (un-scoped) companies, distinguished only by the method constraint.
    ScPlainCompany::insert([
        ['id' => 1, 'name' => 'Live', 'active' => true],
        ['id' => 2, 'name' => 'Dormant', 'active' => false],
    ]);
    ScUser::insert([
        ['id' => 1, 'name' => 'Alice', 'company_id' => 1, 'plain_company_id' => 1],   // Live
        ['id' => 2, 'name' => 'Bob', 'company_id' => 2, 'plain_company_id' => 2],     // trashed / Dormant
        ['id' => 3, 'name' => 'Cara', 'company_id' => 3, 'plain_company_id' => 1],    // inactive / Live
    ]);
});

afterEach(function () {
    foreach (['sc_owners', 'sc_cars', 'sc_mechanics', 'sc_users', 'sc_plain_companies', 'sc_companies', 'sc_regions'] as $t) {
        Schema::dropIfExists($t);
    }
});

// ─── Custom global scope + SoftDeletes on a belongsTo ─────────

it('joins a scoped model as a subquery carrying all its global scopes', function () {
    $table = Table::make()->model(ScUser::class)->columns([
        Column::make('name'),
        Column::make('company.name')->sortable(),
    ]);

    $sql = (new TableQueryService)->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        sortColumn: 'company.name',
    )->toSql();

    // A derived-table join whose subquery applies BOTH the soft-delete and the
    // custom "active" scope.
    expect($sql)->toContain('left join (select * from "sc_companies"')
        ->toContain('"deleted_at" is null')
        ->toContain('"active" = ');
});

it('filters a relation only among rows that survive the global scopes', function () {
    // Ghost is trashed, Sleepy is inactive — both scoped away — so filtering the
    // company name to either matches no user.
    $table = Table::make()->model(ScUser::class)
        ->columns([Column::make('name'), Column::make('company.name')])
        ->filters([SelectFilter::make('company.name')->options([
            'Ghost' => 'Ghost', 'Sleepy' => 'Sleepy', 'Acme' => 'Acme',
        ])]);

    $service = new TableQueryService;

    expect($service->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        filterValues: ['company' => ['name' => 'Ghost']],
    )->get()->pluck('name')->all())->toBe([]);

    expect($service->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        filterValues: ['company' => ['name' => 'Sleepy']],
    )->get()->pluck('name')->all())->toBe([]);

    // Acme survives both scopes → Alice matches.
    expect($service->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        filterValues: ['company' => ['name' => 'Acme']],
    )->get()->pluck('name')->all())->toBe(['Alice']);
});

it('keeps every parent row when sorting by a scoped relation (LEFT JOIN preserved)', function () {
    $table = Table::make()->model(ScUser::class)->columns([
        Column::make('name'),
        Column::make('company.name')->sortable(),
    ]);

    // Bob (trashed) and Cara (inactive) keep appearing; their company reads NULL.
    $rows = (new TableQueryService)->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        sortColumn: 'company.name',
    )->get();

    expect($rows->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Bob', 'Cara']);
});

it('keeps a plain direct join for a relation whose model has no global scopes', function () {
    $table = Table::make()->model(ScUser::class)->columns([
        Column::make('name'),
        Column::make('plainCompany.name')->sortable(),
    ]);

    $sql = (new TableQueryService)->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        sortColumn: 'plainCompany.name',
    )->toSql();

    // Direct table join, no derived subquery.
    expect($sql)->toContain('left join "sc_plain_companies" as')
        ->and($sql)->not->toContain('left join (select');
});

// ─── Global scopes on both hops of a hasOneThrough ───────────

it('scopes both the intermediate and far subqueries of a through relation', function () {
    ScMechanic::insert([['id' => 1, 'name' => 'Manny'], ['id' => 2, 'name' => 'Mo']]);
    ScCar::withoutGlobalScopes()->insert([
        ['id' => 101, 'mechanic_id' => 1, 'deleted_at' => null],
        ['id' => 102, 'mechanic_id' => 2, 'deleted_at' => '2020-01-01 00:00:00'], // trashed car
    ]);
    ScOwner::withoutGlobalScopes()->insert([
        ['id' => 201, 'car_id' => 101, 'name' => 'Zara', 'deleted_at' => null],
        ['id' => 202, 'car_id' => 102, 'name' => 'Anna', 'deleted_at' => null],
    ]);

    $table = Table::make()->model(ScMechanic::class)
        ->columns([Column::make('name'), Column::make('owner.name')])
        ->filters([SelectFilter::make('owner.name')->options(['Zara' => 'Zara', 'Anna' => 'Anna'])]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: ScMechanic::query(),
        table: $table,
        filterValues: ['owner' => ['name' => 'Anna']],
    );

    // Two scoped subqueries (intermediate + far), each soft-delete-guarded.
    expect(substr_count($query->toSql(), 'left join (select'))->toBe(2);
    // Anna's car is trashed → intermediate scope drops it → no match.
    expect($query->get()->pluck('name')->all())->toBe([]);
});

// ─── Constraints declared on the relation method ─────────────

it('applies a constraint declared on the relation method (no global scopes needed)', function () {
    $table = Table::make()->model(ScUser::class)
        ->columns([Column::make('name'), Column::make('activePlainCompany.name')->sortable()])
        ->filters([SelectFilter::make('activePlainCompany.name')->options([
            'Live' => 'Live', 'Dormant' => 'Dormant',
        ])]);

    $service = new TableQueryService;

    // The relation's ->where('active', true) rides inside the subquery.
    $sql = $service->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        sortColumn: 'activePlainCompany.name',
    )->toSql();
    expect($sql)->toContain('left join (select * from "sc_plain_companies" where')
        ->toContain('"active" = ');

    // Dormant is inactive → the constraint hides it → no user matches it.
    expect($service->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        filterValues: ['activePlainCompany' => ['name' => 'Dormant']],
    )->get()->pluck('name')->all())->toBe([]);

    // Live is active → its users match.
    expect($service->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        filterValues: ['activePlainCompany' => ['name' => 'Live']],
    )->get()->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Cara']);
});

it('keeps every parent row when a method-constrained relation is scoped away', function () {
    // Bob points at Dormant (inactive); the constraint hides the company but Bob
    // still appears with a NULL company (LEFT JOIN preserved).
    $table = Table::make()->model(ScUser::class)->columns([
        Column::make('name'),
        Column::make('activePlainCompany.name')->sortable(),
    ]);

    $rows = (new TableQueryService)->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        sortColumn: 'activePlainCompany.name',
    )->get();

    expect($rows->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Bob', 'Cara']);
});

// ─── Depth: combined scope + filter + sort, and nested chains ─

it('applies scope, filter and sort on one relation together (binding order holds)', function () {
    // The subquery's scope binding and the outer filter binding must not collide.
    $table = Table::make()->model(ScUser::class)
        ->columns([Column::make('name'), Column::make('company.name')->sortable()])
        ->filters([SelectFilter::make('company.name')->options(['Acme' => 'Acme'])]);

    $rows = (new TableQueryService)->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        filterValues: ['company' => ['name' => 'Acme']],
        sortColumn: 'company.name',
        sortDirection: 'asc',
    )->get();

    // Only Alice sits at Acme (active, not trashed); the scope + filter agree.
    expect($rows->pluck('name')->all())->toBe(['Alice']);
});

it('sorts through a nested chain whose first hop is a scoped subquery', function () {
    // company (scoped subquery) -> region (plain). The second join must resolve
    // its key against the subquery's exposed columns, not the raw table.
    $table = Table::make()->model(ScUser::class)->columns([
        Column::make('name'),
        Column::make('company.region.name')->sortable(),
    ]);

    $query = (new TableQueryService)->buildQuery(
        baseQuery: ScUser::query(),
        table: $table,
        sortColumn: 'company.region.name',
        sortDirection: 'asc',
    );

    // First hop is a derived table, second hop is a direct join.
    expect($query->toSql())
        ->toContain('left join (select * from "sc_companies"')
        ->toContain('left join "sc_regions"');

    // Alice's company (Acme) survives the scope → region North resolves; Bob and
    // Cara keep their rows with a NULL region (companies scoped away). All present.
    expect($query->get()->pluck('name')->sort()->values()->all())->toBe(['Alice', 'Bob', 'Cara']);
});
