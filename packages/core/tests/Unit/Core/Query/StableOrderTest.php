<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Core\Query\StableOrder;

beforeEach(function () {
    Schema::create('stable_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->string('status');
    });

    $this->model = new class extends Model
    {
        protected $table = 'stable_posts';

        public $timestamps = false;

        protected $guarded = [];
    };

    $this->order = new StableOrder;
});

afterEach(fn () => Schema::dropIfExists('stable_posts'));

it('orders an otherwise unordered query by its key', function () {
    // LIMIT/OFFSET over an unordered query is undefined; this is what makes a
    // page mean the same thing twice.
    $query = $this->model->newQuery();
    $this->order->apply($query);

    expect($query->toSql())->toContain('order by "stable_posts"."id" asc');
});

it('appends the key after the existing ordering, never before it', function () {
    $query = $this->model->newQuery()->orderBy('status');
    $this->order->apply($query);

    // The tiebreaker only decides between rows the real sort calls equal.
    expect($query->toSql())->toContain('order by "status" asc, "stable_posts"."id" asc');
});

it('follows the direction already in force', function () {
    $query = $this->model->newQuery()->orderByDesc('status');
    $this->order->apply($query);

    // Newest-first stays newest-first among rows sharing a value.
    expect($query->toSql())->toContain('"stable_posts"."id" desc');
});

it('takes the direction from the last ordering term', function () {
    $query = $this->model->newQuery()->orderByDesc('status')->orderBy('title');
    $this->order->apply($query);

    expect($query->toSql())->toContain('"stable_posts"."id" asc');
});

it('does not order by the key twice', function (string $column, bool $descending) {
    $query = $this->model->newQuery();
    $descending ? $query->orderByDesc($column) : $query->orderBy($column);

    $this->order->apply($query);

    expect(substr_count($query->toSql(), '"id"'))->toBe(1);
})->with([
    'qualified' => ['stable_posts.id', false],
    'bare' => ['id', false],
    'descending' => ['id', true],
]);

// ── Where a key is not a legal ordering term ────────────────

it('leaves a grouped query alone', function () {
    // PostgreSQL rejects an ordering term that is neither grouped nor
    // aggregated, and MySQL does too under ONLY_FULL_GROUP_BY.
    $query = $this->model->newQuery()->groupBy('status');
    $this->order->apply($query);

    expect($query->toSql())->not->toContain('order by');
});

it('leaves a distinct query alone', function () {
    // PostgreSQL requires every ordering term to appear in the select list.
    $query = $this->model->newQuery()->distinct();
    $this->order->apply($query);

    expect($query->toSql())->not->toContain('order by');
});

it('leaves a union alone', function () {
    // The operands' keys are not one comparable column across the union.
    $query = $this->model->newQuery()->union($this->model->newQuery()->where('status', 'draft'));
    $this->order->apply($query);

    expect($query->toSql())->not->toContain('order by');
});

it('leaves a raw ordering it cannot read alone, and still adds the key', function () {
    // A raw expression could name the key in any shape; guessing wrong either
    // way is worse than a redundant sort term.
    $query = $this->model->newQuery()->orderByRaw('length(title) desc');
    $this->order->apply($query);

    expect($query->toSql())->toContain('length(title) desc')
        ->and($query->toSql())->toContain('"stable_posts"."id"');
});

it('gives every row a distinct position', function () {
    $this->model->newQuery()->insert([
        ['title' => 'a', 'status' => 'draft'],
        ['title' => 'b', 'status' => 'draft'],
        ['title' => 'c', 'status' => 'draft'],
    ]);

    // Sorting by a column whose values repeat leaves ties in whatever order the
    // engine found them — on any engine, not just PostgreSQL.
    $query = $this->model->newQuery()->orderBy('status');
    $this->order->apply($query);

    expect($query->pluck('title')->all())->toBe(['a', 'b', 'c']);
});
