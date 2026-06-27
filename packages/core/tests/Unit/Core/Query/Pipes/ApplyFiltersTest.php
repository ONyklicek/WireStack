<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\Pipes\ApplyFilters;
use NyonCode\WireCore\Core\Query\QueryPlan;

beforeEach(function () {
    Schema::create('filter_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status')->default('active');
        $table->integer('age')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('filter_test_users');
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
