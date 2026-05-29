<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Contracts\QueryPipe;
use NyonCode\WireCore\Core\Query\FilterClause;
use NyonCode\WireCore\Core\Query\JoinClause;
use NyonCode\WireCore\Core\Query\QueryExecutor;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\SortClause;
use NyonCode\WireCore\Core\Query\Strategies\SqliteSearchStrategy;

beforeEach(function () {
    Schema::create('exec_test_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('status')->default('active');
        $table->foreignId('company_id')->nullable();
        $table->timestamps();
    });

    Schema::create('exec_test_companies', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    $this->model = new class extends Model
    {
        protected $table = 'exec_test_users';
    };
});

afterEach(function () {
    Schema::dropIfExists('exec_test_users');
    Schema::dropIfExists('exec_test_companies');
});

it('executes empty plan without modifying builder', function () {
    $executor = new QueryExecutor;
    $plan = new QueryPlan;

    $builder = $this->model->newQuery();
    $result = $executor->execute($builder, $plan);

    expect($result->toRawSql())->toBe('select * from "exec_test_users"');
});

it('executes plan with joins, filters, sorting', function () {
    $executor = new QueryExecutor;

    $plan = new QueryPlan(
        joins: [
            new JoinClause(
                table: 'exec_test_companies',
                alias: 'exec_test_users_company',
                firstColumn: 'exec_test_users.company_id',
                operator: '=',
                secondColumn: 'exec_test_users_company.id',
                type: 'left',
            ),
        ],
        filters: [
            new FilterClause('status', '=', 'active', tableAlias: 'exec_test_users'),
        ],
        sortClauses: [
            new SortClause('name', 'asc', tableAlias: 'exec_test_users'),
        ],
    );

    $builder = $this->model->newQuery();
    $result = $executor->execute($builder, $plan);
    $sql = $result->toRawSql();

    expect($sql)->toContain('left join')
        ->and($sql)->toContain('exec_test_companies')
        ->and($sql)->toContain("'active'")
        ->and($sql)->toContain('order by');
});

it('executes plan with search using auto-detected strategy', function () {
    $executor = new QueryExecutor;

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'exec_test_users'),
            new SearchClause('email', tableAlias: 'exec_test_users'),
        ],
    );

    $builder = $this->model->newQuery();
    $result = $executor->execute($builder, $plan, searchTerm: 'john');
    $sql = $result->toRawSql();

    expect($sql)->toContain('LIKE')
        ->and($sql)->toContain('%john%');
});

it('uses custom search strategy when provided', function () {
    $executor = (new QueryExecutor)->withSearchStrategy(new SqliteSearchStrategy);

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'exec_test_users'),
        ],
    );

    $builder = $this->model->newQuery();
    $result = $executor->execute($builder, $plan, searchTerm: 'test');
    $sql = $result->toRawSql();

    expect($sql)->toContain('LIKE');
});

it('allows custom pipes', function () {
    $customPipe = new class implements QueryPipe
    {
        public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
        {
            $builder->where('custom', '=', 'value');

            return $next($builder, $plan);
        }
    };

    $executor = (new QueryExecutor)->withPipes([$customPipe]);
    $plan = new QueryPlan;

    $builder = $this->model->newQuery();
    $result = $executor->execute($builder, $plan);
    $sql = $result->toRawSql();

    expect($sql)->toContain("'value'");
});

it('executes pipes in order', function () {
    $order = [];

    $pipeA = new class($order) implements QueryPipe
    {
        public function __construct(private array &$order) {}

        public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
        {
            $this->order[] = 'A';

            return $next($builder, $plan);
        }
    };

    $pipeB = new class($order) implements QueryPipe
    {
        public function __construct(private array &$order) {}

        public function handle(Builder $builder, QueryPlan $plan, Closure $next): Builder
        {
            $this->order[] = 'B';

            return $next($builder, $plan);
        }
    };

    $executor = (new QueryExecutor)->withPipes([$pipeA, $pipeB]);
    $plan = new QueryPlan;

    $executor->execute($this->model->newQuery(), $plan);

    expect($order)->toBe(['A', 'B']);
});

it('returns builder without executing query', function () {
    $executor = new QueryExecutor;
    $plan = new QueryPlan(
        filters: [
            new FilterClause('status', '=', 'active'),
        ],
    );

    $result = $executor->execute($this->model->newQuery(), $plan);

    // Result is still a Builder — not yet executed
    expect($result)->toBeInstanceOf(Builder::class);
});

it('is immutable — withPipes returns new instance', function () {
    $executor1 = new QueryExecutor;
    $executor2 = $executor1->withPipes([]);

    expect($executor2)->not->toBe($executor1);
});

it('is immutable — withSearchStrategy returns new instance', function () {
    $executor1 = new QueryExecutor;
    $executor2 = $executor1->withSearchStrategy(new SqliteSearchStrategy);

    expect($executor2)->not->toBe($executor1);
});
