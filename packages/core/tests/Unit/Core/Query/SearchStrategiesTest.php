<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\MySqlSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\PostgresSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\SqliteSearchStrategy;
use NyonCode\WireCore\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Schema::create('strategy_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->model = new class extends Model
    {
        protected $table = 'strategy_test_users';
    };
});

afterEach(function () {
    Schema::dropIfExists('strategy_test_users');
});

// ── SQLite Strategy ─────────────────────────────────────────

it('sqlite strategy applies LIKE with wildcards', function () {
    $strategy = new SqliteSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, 'john');

    $sql = $builder->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->toContain('%john%');
});

it('sqlite strategy handles sql expression', function () {
    $strategy = new SqliteSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "name || ' ' || email");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, 'john');

    $sql = $builder->toRawSql();

    expect($sql)->toContain("name || ' ' || email")
        ->and($sql)->toContain('LIKE');
});

// ── MySQL Strategy (tested against SQLite for SQL generation) ──

it('mysql strategy applies LIKE with wildcards', function () {
    $strategy = new MySqlSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, 'john');

    $sql = $builder->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->toContain('%john%');
});

it('mysql strategy handles sql expression', function () {
    $strategy = new MySqlSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "CONCAT(first_name, ' ', last_name)");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, 'john');

    $sql = $builder->toRawSql();

    expect($sql)->toContain('CONCAT')
        ->and($sql)->toContain('LIKE');
});

// ── PostgreSQL Strategy (tested against SQLite for SQL generation) ──

it('postgres strategy applies ILIKE', function () {
    $strategy = new PostgresSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, 'john');

    $sql = $builder->toRawSql();

    expect($sql)->toContain('ILIKE')
        ->and($sql)->toContain('%john%');
});

it('postgres strategy handles sql expression', function () {
    $strategy = new PostgresSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "first_name || ' ' || last_name");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, 'john');

    $sql = $builder->toRawSql();

    expect($sql)->toContain("first_name || ' ' || last_name")
        ->and($sql)->toContain('ILIKE');
});
