<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Search\LikePattern;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\MySqlSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\PostgresSearchStrategy;
use NyonCode\WireCore\Core\Query\Strategies\SearchStrategies;
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

    // `not->toContain('ILIKE')` is the half that matters: 'ILIKE' contains
    // 'LIKE', so the positive assertion alone passes for a strategy emitting
    // Postgres syntax — which is a syntax error on the engine this one is for.
    expect($sql)->toContain('LIKE')
        ->and($sql)->not->toContain('ILIKE')
        ->and($sql)->toContain('%john%');
});

it('sqlite strategy handles sql expression', function () {
    $strategy = new SqliteSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "name || ' ' || email");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    // Spliced raw, not wrapped: the grammar would quote it as one identifier
    // ("name || ' ' || email"), which is not a column and not valid SQL. Asserting
    // the expression alone cannot tell the two apart — the operator has to sit
    // straight after it.
    expect($sql)->toContain("name || ' ' || email LIKE")
        ->and($sql)->not->toContain('ILIKE');
});

// ── MySQL Strategy (tested against SQLite for SQL generation) ──

it('mysql strategy applies LIKE with wildcards', function () {
    $strategy = new MySqlSearchStrategy;
    $clause = new SearchClause('name', tableAlias: 'strategy_test_users');

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->not->toContain('ILIKE')
        ->and($sql)->toContain('%john%');
});

it('mysql strategy handles sql expression', function () {
    $strategy = new MySqlSearchStrategy;
    $clause = new SearchClause('full_name', sqlExpression: "CONCAT(first_name, ' ', last_name)");

    $builder = $this->model->newQuery();
    $strategy->apply($builder, $clause, LikePattern::contains('john'));

    $sql = $builder->toRawSql();

    expect($sql)->toContain("CONCAT(first_name, ' ', last_name) LIKE")
        ->and($sql)->not->toContain('ILIKE');
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

    expect($sql)->toContain("CAST(first_name || ' ' || last_name AS TEXT) ILIKE");
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

// ── Which strategy a connection gets ─────────────────────────

/*
 * The mapping used to be a private method on the query executor, so the only
 * way to reach it was to run a whole table query — and the second caller that
 * wanted the same answer wrote its own LIKE instead. It matched nothing on
 * Postgres, where LIKE is case-sensitive, which is the failure this section
 * exists to keep from coming back.
 *
 * The driver is read from configuration, not from a live server: Laravel resolves
 * the PDO lazily, so a connection nobody queries needs nothing listening.
 */
function strategyFor(string $driver): object
{
    config()->set("database.connections.strategy_{$driver}", [
        'driver' => $driver,
        'host' => '127.0.0.1',
        'database' => 'nothing',
        'username' => 'nobody',
        'password' => '',
    ]);

    $model = new class extends Model
    {
        protected $table = 'strategy_test_users';
    };

    return SearchStrategies::for($model->setConnection("strategy_{$driver}")->newQuery());
}

it('sends postgres to the strategy that says ILIKE', function () {
    expect(strategyFor('pgsql'))->toBeInstanceOf(PostgresSearchStrategy::class);
});

it('sends mysql and mariadb to the same one', function () {
    expect(strategyFor('mysql'))->toBeInstanceOf(MySqlSearchStrategy::class)
        ->and(strategyFor('mariadb'))->toBeInstanceOf(MySqlSearchStrategy::class);
});

it('falls back to LIKE for anything else', function () {
    // SQLite by name, and an engine nobody has taught it about: LIKE is the
    // portable answer, and a driver this does not know is not a reason to fail.
    expect(strategyFor('sqlite'))->toBeInstanceOf(SqliteSearchStrategy::class)
        ->and(strategyFor('sqlsrv'))->toBeInstanceOf(SqliteSearchStrategy::class);
});
