<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\JoinClause;
use NyonCode\WireCore\Core\Query\Pipes\ApplyRelations;
use NyonCode\WireCore\Core\Query\QueryPlan;

beforeEach(function () {
    Schema::create('test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });

    Schema::create('test_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function () {
    Schema::dropIfExists('test_users');
    Schema::dropIfExists('test_companies');
});

it('applies join clauses to builder', function () {
    $model = new class extends Model
    {
        protected $table = 'test_users';
    };

    $plan = new QueryPlan(
        joins: [
            new JoinClause(
                table: 'test_companies',
                alias: 'test_users_company',
                firstColumn: 'test_users.company_id',
                operator: '=',
                secondColumn: 'test_users_company.id',
                type: 'left',
            ),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyRelations;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('left join')
        ->and($sql)->toContain('test_companies')
        ->and($sql)->toContain('test_users_company');
});

it('skips when no joins in plan', function () {
    $model = new class extends Model
    {
        protected $table = 'test_users';
    };

    $plan = new QueryPlan;
    $builder = $model->newQuery();
    $pipe = new ApplyRelations;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->not->toContain('join');
});

it('applies multiple joins', function () {
    $model = new class extends Model
    {
        protected $table = 'test_users';
    };

    $plan = new QueryPlan(
        joins: [
            new JoinClause(
                table: 'test_companies',
                alias: 'test_users_company',
                firstColumn: 'test_users.company_id',
                operator: '=',
                secondColumn: 'test_users_company.id',
                type: 'left',
            ),
            new JoinClause(
                table: 'test_companies',
                alias: 'test_users_parent',
                firstColumn: 'test_users.company_id',
                operator: '=',
                secondColumn: 'test_users_parent.id',
                type: 'inner',
            ),
        ],
    );

    $builder = $model->newQuery();
    $pipe = new ApplyRelations;
    $result = $pipe->handle($builder, $plan, fn (Builder $b, QueryPlan $p) => $b);

    $sql = $result->toRawSql();

    expect($sql)->toContain('left join')
        ->and($sql)->toContain('inner join');
});
