<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Pipes\ApplySearch;
use NyonCode\WireCore\Core\Query\QueryPlan;
use NyonCode\WireCore\Core\Query\Search\SearchConfig;
use NyonCode\WireCore\Core\Query\Search\SearchTermParser;
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

// ── Tokenized terms ─────────────────────────────────────────
//
// Each token gets its own group and the groups AND: a row survives only when
// every word matched *something*, which is what lets a first name in one column
// and a surname in another match together.

it('ANDs one group per token and ORs the columns inside it', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $model->newQuery()->insert([
        ['name' => 'Ada', 'email' => 'lovelace@example.com'],
        ['name' => 'Ada', 'email' => 'byron@example.com'],
        ['name' => 'Grace', 'email' => 'lovelace@example.org'],
    ]);

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'search_test_users'),
            new SearchClause('email', tableAlias: 'search_test_users'),
        ],
    );

    $term = (new SearchTermParser)->parse('ada lovelace', SearchConfig::make()->tokenize());
    $pipe = new ApplySearch(new SqliteSearchStrategy, $term);
    $result = $pipe->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    // Only the row that has "ada" somewhere AND "lovelace" somewhere.
    expect($result->pluck('email')->all())->toBe(['lovelace@example.com']);
});

it('matches an unsplit term as one substring, as it always did', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $model->newQuery()->insert([
        ['name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
        ['name' => 'Ada', 'email' => 'lovelace@example.com'],
    ]);

    $plan = new QueryPlan(
        searchClauses: [
            new SearchClause('name', tableAlias: 'search_test_users'),
            new SearchClause('email', tableAlias: 'search_test_users'),
        ],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, 'Ada Lovelace');
    $result = $pipe->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect($result->pluck('email')->all())->toBe(['ada@example.com']);
});

it('escapes a typed percent instead of matching everything', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $model->newQuery()->insert([
        ['name' => '50% off', 'email' => 'sale@example.com'],
        ['name' => 'Ada', 'email' => 'ada@example.com'],
    ]);

    $plan = new QueryPlan(
        searchClauses: [new SearchClause('name', tableAlias: 'search_test_users')],
    );

    $pipe = new ApplySearch(new SqliteSearchStrategy, '50%');
    $result = $pipe->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect($result->pluck('email')->all())->toBe(['sale@example.com']);
});

it('falls back to literal text when no column can answer a comparison', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $model->newQuery()->insert([
        ['name' => 'over >100 units', 'email' => 'bulk@example.com'],
        ['name' => 'Ada', 'email' => 'ada@example.com'],
    ]);

    $plan = new QueryPlan(
        searchClauses: [new SearchClause('name', tableAlias: 'search_test_users')],
    );

    $term = (new SearchTermParser)->parse('>100', SearchConfig::make()->ranges());
    $pipe = new ApplySearch(new SqliteSearchStrategy, $term);
    $result = $pipe->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    // Nothing numeric is searchable, so an empty group would have matched every
    // row. The text the user typed is searched instead.
    expect($result->pluck('email')->all())->toBe(['bulk@example.com']);
});

it('passes each token to a host search callback', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $seen = [];
    $plan = new QueryPlan(searchClauses: []);

    $term = (new SearchTermParser)->parse('ada lovelace', SearchConfig::make()->tokenize());
    $pipe = new ApplySearch(new SqliteSearchStrategy, $term, [
        function (Builder $query, string $search) use (&$seen): void {
            $seen[] = $search;
            $query->where('name', 'LIKE', "%{$search}%");
        },
    ]);

    $pipe->handle($model->newQuery(), $plan, fn (Builder $b, QueryPlan $p) => $b);

    expect($seen)->toBe(['ada', 'lovelace']);
});

it('hands a comparison token to a host callback as the raw text', function () {
    $model = new class extends Model
    {
        protected $table = 'search_test_users';

        public $timestamps = false;

        protected $guarded = [];
    };

    $seen = null;
    $term = (new SearchTermParser)->parse('>100', SearchConfig::make()->ranges());
    $pipe = new ApplySearch(new SqliteSearchStrategy, $term, [
        function (Builder $query, string $search) use (&$seen): void {
            $seen = $search;
        },
    ]);

    $pipe->handle($model->newQuery(), new QueryPlan(searchClauses: []), fn (Builder $b, QueryPlan $p) => $b);

    expect($seen)->toBe('>100');
});
