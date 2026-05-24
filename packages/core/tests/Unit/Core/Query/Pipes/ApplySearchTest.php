<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Pipes\ApplySearch;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\SqliteSearchStrategy;

beforeEach(function () {
    Schema::create('search_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('search_test_users');
});

it('applies search with LIKE across searchable columns', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';
    };

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'search_test_users'),
            new SearchClause('email', tableAlias: 'search_test_users'),
        ],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, 'john');
    $builder = $model->newQuery();
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->toContain('%john%');
});

it('wraps search in grouped where', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';
    };

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'search_test_users'),
            new SearchClause('email', tableAlias: 'search_test_users'),
        ],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, 'john');
    $builder = $model->newQuery();
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    // The two OR clauses should be wrapped in a grouped (... OR ...)
    expect($sql)->toContain('(');
});

it('skips search when no search term', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';
    };

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'search_test_users'),
        ],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, null);
    $builder = $model->newQuery();
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('LIKE');
});

it('skips search when empty search term', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';
    };

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'search_test_users'),
        ],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, '');
    $builder = $model->newQuery();
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('LIKE');
});

it('skips search when no search clauses in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';
    };

    $plan = new QueryPlan;

    $pipe = new ApplySearch(new SqliteSearchStrategy, 'john');
    $builder = $model->newQuery();
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('LIKE');
});

it('handles sql expression search', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';
    };

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('full_name', sqlExpression: "name || ' ' || email"),
        ],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, 'john');
    $builder = $model->newQuery();
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain("name || ' ' || email");
});
