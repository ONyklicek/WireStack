<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Pipes\ApplySorting;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('sort_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('sort_test_users');
});

it('applies simple sort', function () {
    $model = new class extends Model
    {
        protected $table = 'sort_test_users';
    };

    $plan = new QueryPlan(
        sortClauses: [
            new SortClause('name', 'asc', tableAlias: 'sort_test_users'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplySorting;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('order by')
        ->and($sql)->toContain('sort_test_users');
});

it('applies descending sort', function () {
    $model = new class extends Model
    {
        protected $table = 'sort_test_users';
    };

    $plan = new QueryPlan(
        sortClauses: [
            new SortClause('name', 'desc'),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplySorting;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('desc');
});

it('applies sql expression sort', function () {
    $model = new class extends Model
    {
        protected $table = 'sort_test_users';
    };

    $plan = new QueryPlan(
        sortClauses: [
            new SortClause('full_name', 'asc', sqlExpression: "CONCAT(first_name, ' ', last_name)"),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplySorting;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('CONCAT');
});

it('skips when no sorts in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'sort_test_users';
    };

    $plan = new QueryPlan;
    $builder = $model->newQuery();
    $pipe = new ApplySorting;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('order by');
});
