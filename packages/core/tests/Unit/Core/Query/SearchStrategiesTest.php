<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Search\LikePattern;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\MySqlSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\PostgresSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\SqliteSearchStrategy;

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
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->toContain('%john%');
});

it('sqlite strategy handles sql expression', function () {
    $strategy = new SqliteSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "name || ' ' || email");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain("name || ' ' || email")
        ->and($sql)->toContain('LIKE');
});

// ── MySQL Strategy (tested against SQLite for SQL generation) ──

it('mysql strategy applies LIKE with wildcards', function () {
    $strategy = new MySqlSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->toContain('%john%');
});

it('mysql strategy handles sql expression', function () {
    $strategy = new MySqlSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "CONCAT(first_name, ' ', last_name)");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain('CONCAT')
        ->and($sql)->toContain('LIKE');
});

// ── PostgreSQL Strategy (tested against SQLite for SQL generation) ──

it('postgres strategy applies ILIKE', function () {
    $strategy = new PostgresSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain('ILIKE')
        ->and($sql)->toContain('%john%');
});

it('postgres strategy handles sql expression', function () {
    $strategy = new PostgresSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "first_name || ' ' || last_name");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain("first_name || ' ' || last_name")
        ->and($sql)->toContain('ILIKE');
});

// ── The escape clause ───────────────────────────────────────
//
// Every predicate declares its escape character. SQLite's LIKE has none by
// default, so without the clause an escaped `%` would match the backslash-ish
// literal rather than the percent sign the user typed.

it('every strategy declares the escape character on the predicate', function (object $strategy) {
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    expect($builder->toRawSql())->toContain("ESCAPE '".LikePattern::ESCAPE."'");
})->with([
    'sqlite' => fn () => new SqliteSearchStrategy,
    'mysql' => fn () => new MySqlSearchStrategy,
    'postgres' => fn () => new PostgresSearchStrategy,
]);

it('declares the escape character on a sql-expression predicate too', function (object $strategy) {
    $clause = new SearchClause('full_name', sqlExpression: "first_name || ' ' || last_name");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    expect($builder->toRawSql())->toContain("ESCAPE '".LikePattern::ESCAPE."'");
})->with([
    'sqlite' => fn () => new SqliteSearchStrategy,
    'mysql' => fn () => new MySqlSearchStrategy,
    'postgres' => fn () => new PostgresSearchStrategy,
]);

// The escape character must not be a backslash: MySQL and MariaDB parse the
// backslash inside a string literal as escaping the closing quote, so
// `ESCAPE '\'` is a syntax error there. An earlier attempt shipped exactly
// that, passed on SQLite and PostgreSQL, and broke every search on MariaDB.
it('does not escape with a backslash, which MariaDB cannot parse', function () {
    expect(LikePattern::ESCAPE)->not->toBe('\\');
});

it('quotes the column rather than interpolating it raw', function () {
    $strategy = new SqliteSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    expect($builder->toSql())->toContain('"strategy_test_users"."name"');
});

// PostgreSQL refuses ILIKE against a non-text column outright ("operator does
// not exist: numeric ~~* text"), so a searchable number or date column took the
// page down there while working fine on MySQL and SQLite.
it('casts the column to text so postgres can match a number or a date', function () {
    $strategy = new PostgresSearchStrategy;
    $clause = new SearchClause('amount', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('50'));

    expect($builder->toSql())->toContain('CAST("strategy_test_users"."amount" AS TEXT) ILIKE');
});

it('casts a sql expression to text as well', function () {
    $strategy = new PostgresSearchStrategy;
    $clause = new SearchClause('total', sqlExpression: 'amount * 2');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('50'));

    expect($builder->toSql())->toContain('CAST(amount * 2 AS TEXT) ILIKE');
});
