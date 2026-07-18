<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\Pipes\ApplyFilters;
use NyonCode\WireCore\Core\Query\QueryPlan;

class ApplyFiltersRelCompany extends Model
{
    protected $table = 'af_companies';

    public $timestamps = false;

    protected $guarded = [];
}

class ApplyFiltersRelComment extends Model
{
    protected $table = 'af_comments';

    public $timestamps = false;

    protected $guarded = [];

    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }
}

class ApplyFiltersRelUser extends Model
{
    protected $table = 'af_users';

    public $timestamps = false;

    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(ApplyFiltersRelCompany::class, 'company_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ApplyFiltersRelComment::class, 'user_id');
    }

    /** A method that is not an Eloquent relation — the safety walk must reject it. */
    public function notARelation(): string
    {
        return 'nope';
    }

    /** A relation method that throws while resolving — the walk must swallow it. */
    public function explodes(): void
    {
        throw new RuntimeException('boom');
    }
}

beforeEach(function () {
    Schema::create('filter_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('active');
        $table->integer('age')->nullable();
        $table->timestamps();
    });

    Schema::create('af_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('af_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('company_id')->nullable();
    });

    Schema::create('af_comments', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable();
        $table->string('body')->nullable();
        $table->nullableMorphs('commentable');
    });
});

afterEach(function () {
    Schema::dropIfExists('filter_test_users');
    Schema::dropIfExists('af_companies');
    Schema::dropIfExists('af_users');
    Schema::dropIfExists('af_comments');
});

it('applies simple equality filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(
        filters: [
            new FilterClause('status', '=', 'active', tableAlias: 'filter_test_users'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain("'active'");
});

it('applies IS NULL filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(
        filters: [
            new FilterClause('age', 'IS NULL'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('is null');
});

it('applies IS NOT NULL filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(
        filters: [
            new FilterClause('age', 'IS NOT NULL'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('is not null');
});

it('applies IN filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(
        filters: [
            new FilterClause('status', 'IN', ['active', 'pending']),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('in');
});

it('applies NOT IN filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(filters: [new FilterClause('status', 'NOT IN', ['banned'])]);

    $sql = strtolower((new ApplyFilters)
        ->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b)
        ->toRawSql());

    expect($sql)->toContain('not in');
});

it('applies BETWEEN filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(
        filters: [
            new FilterClause('age', 'BETWEEN', [18, 65]),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('between');
});

it('degrades a BETWEEN with only a lower bound to a >= comparison', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(filters: [new FilterClause('age', 'BETWEEN', [18, null])]);

    $sql = (new ApplyFilters)
        ->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b)
        ->toRawSql();

    expect($sql)->toContain('>=')
        ->and(strtolower($sql))->not->toContain('between');
});

it('degrades a BETWEEN with only an upper bound to a <= comparison', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(filters: [new FilterClause('age', 'BETWEEN', [null, 65])]);

    $sql = (new ApplyFilters)
        ->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b)
        ->toRawSql();

    expect($sql)->toContain('<=')
        ->and(strtolower($sql))->not->toContain('between');
});

it('skips a BETWEEN with no bounds and a NOT BETWEEN with a single bound', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(filters: [
        new FilterClause('age', 'BETWEEN', [null, null]),
        new FilterClause('age', 'NOT BETWEEN', [18, null]),
    ]);

    $sql = strtolower((new ApplyFilters)
        ->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b)
        ->toRawSql());

    // Neither incomplete clause should emit any where condition.
    expect($sql)->not->toContain('between')
        ->and($sql)->not->toContain('where');
});

it('applies a NOT BETWEEN with both bounds', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(filters: [new FilterClause('age', 'NOT BETWEEN', [18, 65])]);

    $sql = strtolower((new ApplyFilters)
        ->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b)
        ->toRawSql());

    expect($sql)->toContain('not between');
});

it('applies sql expression filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan(
        filters: [
            new FilterClause('total', '>=', 100, sqlExpression: 'LENGTH(name)'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('LENGTH(name)');
});

it('skips when no filters in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    $plan = new QueryPlan;
    $builder = $model->newQuery();
    $pipe = new ApplyFilters;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('where');
});

// ── Relation filters (native whereHas) ───────────────────────

it('applies a belongsTo relation filter via whereHas', function () {
    $acme = ApplyFiltersRelCompany::create(['name' => 'Acme']);
    $globex = ApplyFiltersRelCompany::create(['name' => 'Globex']);
    ApplyFiltersRelUser::create(['name' => 'Alice', 'company_id' => $acme->id]);
    ApplyFiltersRelUser::create(['name' => 'Bob', 'company_id' => $globex->id]);

    $plan = new QueryPlan(filters: [
        new FilterClause('name', '=', 'Acme', isRelation: true, relationPath: 'company'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->toContain('exists')
        ->and($result->pluck('name')->all())->toBe(['Alice']);
});

it('applies a to-many relation filter that a join could not express', function () {
    $alice = ApplyFiltersRelUser::create(['name' => 'Alice']);
    $bob = ApplyFiltersRelUser::create(['name' => 'Bob']);
    ApplyFiltersRelComment::create(['user_id' => $alice->id, 'body' => 'hello world']);
    ApplyFiltersRelComment::create(['user_id' => $bob->id, 'body' => 'goodbye']);

    $plan = new QueryPlan(filters: [
        new FilterClause('body', 'LIKE', '%hello%', isRelation: true, relationPath: 'comments'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect($result->pluck('name')->all())->toBe(['Alice']);
});

// ── Aggregate filters (whereHas count / exists, never HAVING) ─

it('filters by an aggregate count via a whereHas count comparison', function () {
    $alice = ApplyFiltersRelUser::create(['name' => 'Alice']);
    $bob = ApplyFiltersRelUser::create(['name' => 'Bob']);
    ApplyFiltersRelComment::create(['user_id' => $alice->id, 'body' => 'a']);
    ApplyFiltersRelComment::create(['user_id' => $alice->id, 'body' => 'b']);
    ApplyFiltersRelComment::create(['user_id' => $bob->id, 'body' => 'c']);

    $plan = new QueryPlan(filters: [
        new FilterClause('comments_count', '>', 1, isAggregate: true, aggregateRelation: 'comments', aggregateFunction: 'count'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    // A WHERE over the count subquery, never HAVING.
    expect(strtolower($result->toRawSql()))->not->toContain('having')
        ->and($result->pluck('name')->all())->toBe(['Alice']);
});

it('filters by an exists aggregate via whereHas / whereDoesntHave', function () {
    $alice = ApplyFiltersRelUser::create(['name' => 'Alice']);
    ApplyFiltersRelUser::create(['name' => 'Bob']);
    ApplyFiltersRelComment::create(['user_id' => $alice->id, 'body' => 'a']);

    $has = new QueryPlan(filters: [
        new FilterClause('comments_exists', '=', true, isAggregate: true, aggregateRelation: 'comments', aggregateFunction: 'exists'),
    ]);
    $hasResult = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $has, fn (Builder $b, QueryPlan $p) => $b);
    expect($hasResult->pluck('name')->all())->toBe(['Alice']);

    $missing = new QueryPlan(filters: [
        new FilterClause('comments_exists', '=', false, isAggregate: true, aggregateRelation: 'comments', aggregateFunction: 'exists'),
    ]);
    $missingResult = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $missing, fn (Builder $b, QueryPlan $p) => $b);
    expect($missingResult->pluck('name')->all())->toBe(['Bob']);
});

it('skips an unsupported sum aggregate filter instead of using HAVING', function () {
    ApplyFiltersRelUser::create(['name' => 'Alice']);
    ApplyFiltersRelUser::create(['name' => 'Bob']);

    // sum/avg/min/max have no native whereHas primitive → skipped (no HAVING).
    $plan = new QueryPlan(filters: [
        new FilterClause('comments_sum_body', '>', 10, isAggregate: true, aggregateRelation: 'comments', aggregateFunction: 'sum'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->not->toContain('having')
        ->and($result->count())->toBe(2);
});

it('skips an aggregate filter whose relation is not whereHas-safe', function () {
    ApplyFiltersRelUser::create(['name' => 'Alice']);

    $plan = new QueryPlan(filters: [
        new FilterClause('ghost_count', '>', 0, isAggregate: true, aggregateRelation: 'ghost', aggregateFunction: 'count'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect($result->count())->toBe(1);
});

it('applies an OR relation filter via orWhereHas', function () {
    $acme = ApplyFiltersRelCompany::create(['name' => 'Acme']);
    ApplyFiltersRelUser::create(['name' => 'Alice', 'company_id' => $acme->id]);
    ApplyFiltersRelUser::create(['name' => 'Bob']);

    // name = 'Nobody' OR (has an Acme company) → only Alice matches the OR arm.
    $plan = new QueryPlan(filters: [
        new FilterClause('name', '=', 'Nobody'),
        new FilterClause('name', '=', 'Acme', isRelation: true, relationPath: 'company', boolean: 'or'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->toContain('or exists')
        ->and($result->pluck('name')->all())->toBe(['Alice']);
});

it('skips a relation filter whose path is not a real relation method', function () {
    ApplyFiltersRelUser::create(['name' => 'Alice']);

    $plan = new QueryPlan(filters: [
        new FilterClause('x', '=', 'y', isRelation: true, relationPath: 'ghost'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->not->toContain('exists')
        ->and($result->count())->toBe(1);
});

it('skips a relation filter whose segment is not an Eloquent relation', function () {
    ApplyFiltersRelUser::create(['name' => 'Alice']);

    // "not_a_relation" → notARelation() returns a string, not a Relation.
    $plan = new QueryPlan(filters: [
        new FilterClause('x', '=', 'y', isRelation: true, relationPath: 'not_a_relation'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->not->toContain('exists')
        ->and($result->count())->toBe(1);
});

it('skips a relation filter when resolving the relation throws', function () {
    ApplyFiltersRelUser::create(['name' => 'Alice']);

    $plan = new QueryPlan(filters: [
        new FilterClause('x', '=', 'y', isRelation: true, relationPath: 'explodes'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelUser)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->not->toContain('exists')
        ->and($result->count())->toBe(1);
});

it('skips a relation filter whose path contains a MorphTo instead of throwing', function () {
    ApplyFiltersRelComment::create(['body' => 'one']);
    ApplyFiltersRelComment::create(['body' => 'two']);

    // commentable is a MorphTo — whereHas cannot constrain it, so the filter is
    // dropped (all rows returned) rather than erroring.
    $plan = new QueryPlan(filters: [
        new FilterClause('title', '=', 'x', isRelation: true, relationPath: 'commentable'),
    ]);

    $result = (new ApplyFilters)
        ->handle((new ApplyFiltersRelComment)->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect(strtolower($result->toRawSql()))->not->toContain('exists')
        ->and($result->count())->toBe(2);
});

it('rejects an invalid operator on a raw sql-expression filter', function () {
    $model = new class extends Model
    {
        protected $table = 'filter_test_users';
    };

    // The operator is interpolated into raw SQL, so an operator outside the
    // canonical allow-list must be rejected rather than spliced into the query.
    $plan = new QueryPlan(
        filters: [
            new FilterClause('total', '; DROP TABLE users; --', 1, sqlExpression: 'LENGTH(name)'),
        ],
    );

    $pipe = new ApplyFilters;

    expect(fn () => $pipe->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b))
        ->toThrow(InvalidArgumentException::class);
});
