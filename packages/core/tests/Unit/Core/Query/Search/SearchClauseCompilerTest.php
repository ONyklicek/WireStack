<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\Search\SearchClauseCompiler;
use NyonCode\WireCore\Core\Query\Search\SearchConfig;
use NyonCode\WireCore\Core\Query\Search\SearchTermParser;
use NyonCode\WireCore\Core\Query\Search\SearchValueType;
use NyonCode\WireCore\Core\Query\SearchClause;
use NyonCode\WireCore\Core\Query\Strategies\SqliteSearchStrategy;

beforeEach(function () {
    Schema::create('compiler_orders', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->decimal('amount', 10, 2);
        $table->dateTime('placed_at');
    });

    $this->model = new class extends Model
    {
        protected $table = 'compiler_orders';

        public $timestamps = false;

        protected $guarded = [];
    };

    $this->model->newQuery()->insert([
        ['title' => 'small', 'amount' => 50.00, 'placed_at' => '2026-01-30 08:00:00'],
        ['title' => 'medium', 'amount' => 150.00, 'placed_at' => '2026-01-31 23:30:00'],
        ['title' => 'large', 'amount' => 900.00, 'placed_at' => '2026-03-15 12:00:00'],
    ]);

    $this->compiler = new SearchClauseCompiler;
    $this->strategy = new SqliteSearchStrategy;

    // Compile the token the same way the pipe does: parse it, then apply it to
    // one clause, so the tests describe what a user typing that term gets.
    $this->titlesFor = function (string $typed, SearchClause $clause): array {
        $token = (new SearchTermParser)->parse($typed, SearchConfig::make()->ranges())->tokens[0];
        $query = $this->model->newQuery();

        $applied = $this->compiler->apply($query, $clause, $token, $this->strategy);

        return [$applied, $query->pluck('title')->all()];
    };
});

afterEach(function () {
    Schema::dropIfExists('compiler_orders');
});

// ── Numeric columns ─────────────────────────────────────────

$numeric = fn () => new SearchClause('amount', valueType: SearchValueType::Numeric);

it('compares a numeric column', function (string $typed, array $expected) use ($numeric) {
    [$applied, $titles] = ($this->titlesFor)($typed, $numeric());

    expect($applied)->toBeTrue()
        ->and($titles)->toBe($expected);
})->with([
    ['>100', ['medium', 'large']],
    ['>=150', ['medium', 'large']],
    ['<100', ['small']],
    ['<=150', ['small', 'medium']],
    ['=150', ['medium']],
    ['100..200', ['medium']],
    ['150..', ['medium', 'large']],
    ['..100', ['small']],
]);

it('reads a range typed the wrong way round as the range it means', function () use ($numeric) {
    [, $titles] = ($this->titlesFor)('200..100', $numeric());

    expect($titles)->toBe(['medium']);
});

// ── Date columns ────────────────────────────────────────────

$date = fn () => new SearchClause('placed_at', valueType: SearchValueType::Date);

it('treats a typed day as the whole day', function (string $typed, array $expected) use ($date) {
    [$applied, $titles] = ($this->titlesFor)($typed, $date());

    expect($applied)->toBeTrue()
        ->and($titles)->toBe($expected);
})->with([
    // 23:30 on the 31st is still the 31st — the classic off-by-a-day.
    ['=2026-01-31', ['medium']],
    ['<=2026-01-31', ['small', 'medium']],
    ['>=2026-01-31', ['medium', 'large']],
    ['>2026-01-31', ['large']],
    ['<2026-01-31', ['small']],
    ['2026-01-01..2026-01-31', ['small', 'medium']],
]);

it('widens a typed month and year to their whole span', function (string $typed, array $expected) use ($date) {
    [, $titles] = ($this->titlesFor)($typed, $date());

    expect($titles)->toBe($expected);
})->with([
    ['=2026-01', ['small', 'medium']],
    ['=2026', ['small', 'medium', 'large']],
    ['2026-01..2026-01', ['small', 'medium']],
]);

it('understands a day-first date', function () use ($date) {
    [, $titles] = ($this->titlesFor)('=31.01.2026', $date());

    expect($titles)->toBe(['medium']);
});

it('refuses a date that does not exist', function () use ($date) {
    [$applied, $titles] = ($this->titlesFor)('=2026-02-31', $date());

    // Carbon would roll 31 February into March and silently search the wrong
    // span, so the clause declines the token instead.
    expect($applied)->toBeFalse()
        ->and($titles)->toHaveCount(3);
});

// ── Columns that cannot answer ──────────────────────────────

it('declines a comparison asked of a text column', function () {
    $clause = new SearchClause('title', valueType: SearchValueType::Text);
    [$applied, $titles] = ($this->titlesFor)('>100', $clause);

    expect($applied)->toBeFalse()
        ->and($titles)->toHaveCount(3);
});

it('declines a date comparison asked of a numeric column', function () use ($numeric) {
    [$applied] = ($this->titlesFor)('>2026-01-31', $numeric());

    // `2026-01-31` is not a number, so the numeric column has nothing to compare.
    expect($applied)->toBeFalse();
});

it('matches text against any column type', function () use ($numeric) {
    $token = (new SearchTermParser)->parse('50')->tokens[0];
    $query = $this->model->newQuery();

    // A numeric column still answers a plain substring search — 150.00 contains
    // "50" just as 50.00 does.
    expect($this->compiler->apply($query, $numeric(), $token, $this->strategy))->toBeTrue()
        ->and($query->pluck('title')->all())->toBe(['small', 'medium']);
});

it('compares a fractional value against a sql expression', function () {
    // The float would be bound as a string and compared as text without the
    // literal operand, silently matching nothing.
    $clause = new SearchClause('total', sqlExpression: 'amount * 2', valueType: SearchValueType::Numeric);
    [$applied, $titles] = ($this->titlesFor)('>300.5', $clause);

    expect($applied)->toBeTrue()
        ->and($titles)->toBe(['large']);
});

it('refuses a number too large to compare', function () use ($numeric) {
    [$applied] = ($this->titlesFor)('>1e400', $numeric());

    expect($applied)->toBeFalse();
});

// ── SQL expressions ─────────────────────────────────────────

it('compares against a sql expression', function () {
    $clause = new SearchClause('total', sqlExpression: 'amount * 2', valueType: SearchValueType::Numeric);
    [$applied, $titles] = ($this->titlesFor)('>500', $clause);

    expect($applied)->toBeTrue()
        ->and($titles)->toBe(['large']);
});

it('ranges over a sql expression', function () {
    $clause = new SearchClause('total', sqlExpression: 'amount * 2', valueType: SearchValueType::Numeric);
    [$applied, $titles] = ($this->titlesFor)('200..400', $clause);

    expect($applied)->toBeTrue()
        ->and($titles)->toBe(['medium']);
});

// ── Structured codes ────────────────────────────────────────

it('ranges over a code within its series', function () {
    Schema::create('compiler_codes', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
    });

    $codes = new class extends Model
    {
        protected $table = 'compiler_codes';

        public $timestamps = false;

        protected $guarded = [];
    };

    $codes->newQuery()->insert([
        ['reference' => '8866 01'],
        ['reference' => '8866 05'],
        ['reference' => '8866 12'],
        ['reference' => '9900 05'],
    ]);

    $clause = new SearchClause('reference', valueType: SearchValueType::Code);
    $token = (new SearchTermParser)
        ->parse('8866 01..08', SearchConfig::make()->tokenize()->ranges())
        ->tokens[1];

    $query = $codes->newQuery();
    $applied = (new SearchClauseCompiler)->apply($query, $clause, $token, new SqliteSearchStrategy);

    // One BETWEEN over the completed bounds — not one LIKE per number.
    expect($applied)->toBeTrue()
        ->and($query->pluck('reference')->all())->toBe(['8866 01', '8866 05'])
        ->and($query->toSql())->toContain('between');

    Schema::dropIfExists('compiler_codes');
});

it('completes a range typed across a width boundary', function () {
    // `50..100` only means anything if the stored tail is three digits wide, so
    // the short bound is padded the way the value itself is stored. Compared at
    // two different widths, `8866 050` would land outside its own range.
    Schema::create('compiler_wide_codes', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
    });

    $codes = new class extends Model
    {
        protected $table = 'compiler_wide_codes';

        public $timestamps = false;

        protected $guarded = [];
    };

    $codes->newQuery()->insert([
        ['reference' => '8866 049'],
        ['reference' => '8866 050'],
        ['reference' => '8866 099'],
        ['reference' => '8866 100'],
        ['reference' => '8866 101'],
    ]);

    $clause = new SearchClause('reference', valueType: SearchValueType::Code);
    $token = (new SearchTermParser)
        ->parse('8866 50..100', SearchConfig::make()->tokenize()->ranges())
        ->tokens[1];

    $query = $codes->newQuery();
    $applied = (new SearchClauseCompiler)->apply($query, $clause, $token, new SqliteSearchStrategy);

    expect($applied)->toBeTrue()
        ->and($query->pluck('reference')->all())->toBe(['8866 050', '8866 099', '8866 100']);

    Schema::dropIfExists('compiler_wide_codes');
});

it('compares a code one-sidedly within its series', function () {
    $clause = new SearchClause('reference', valueType: SearchValueType::Code);
    $token = (new SearchTermParser)
        ->parse('8866 >=05', SearchConfig::make()->tokenize()->ranges())
        ->tokens[1];

    $query = $this->model->newQuery();
    $applied = (new SearchClauseCompiler)->apply($query, $clause, $token, $this->strategy);

    expect($applied)->toBeTrue()
        ->and($query->getQuery()->bindings['where'])->toBe(['8866 05']);
});

it('lets a numeric column read the same token without the series', function () use ($numeric) {
    // `8866 01..08` means one thing to a code column and another to a numeric
    // one; both readings survive because neither had to win at parse time.
    $token = (new SearchTermParser)
        ->parse('8866 01..08', SearchConfig::make()->tokenize()->ranges())
        ->tokens[1];

    $query = $this->model->newQuery();
    $applied = (new SearchClauseCompiler)->apply($query, $numeric(), $token, $this->strategy);

    expect($applied)->toBeTrue()
        ->and($query->getQuery()->bindings['where'])->toBe([1, 8]);
});
