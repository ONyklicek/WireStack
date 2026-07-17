<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Services\SummaryCalculator;
use NyonCode\WireTable\Services\SummaryFormatter;
use NyonCode\WireTable\Support\SummaryFormat;
use NyonCode\WireTable\Support\SummaryTarget;

/*
 * The SQL side of SummaryCalculator, which the pure-statistics unit test cannot
 * reach: aggregating a real column in the database, and aggregating a rollup
 * column — a withSum/withCount subselect alias — over a derived table. A rollup
 * cannot be summed like a real column: its value is a subquery, so it has to be
 * wrapped as `SELECT ... FROM (<query>)` first. Getting that wrong does not
 * error, it silently sums the wrong thing, which is why these paths are checked
 * against real numbers.
 */

class ScqOrder extends Model
{
    protected $table = 'scq_orders';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<ScqItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ScqItem::class, 'order_id');
    }
}

class ScqItem extends Model
{
    protected $table = 'scq_items';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('scq_orders', function (Blueprint $t) {
        $t->id();
        $t->integer('total');
    });
    Schema::create('scq_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('order_id');
        $t->integer('amount');
    });

    ScqOrder::insert([
        ['id' => 1, 'total' => 10],
        ['id' => 2, 'total' => 20],
        ['id' => 3, 'total' => 30],
    ]);
    ScqItem::insert([
        ['order_id' => 1, 'amount' => 100],
        ['order_id' => 1, 'amount' => 50],  // order 1 rollup = 150
        ['order_id' => 2, 'amount' => 300], // order 2 rollup = 300
        ['order_id' => 3, 'amount' => 200], // order 3 rollup = 200
    ]);

    $this->calc = new SummaryCalculator(new SummaryFormatter);
});

afterEach(function () {
    Schema::dropIfExists('scq_items');
    Schema::dropIfExists('scq_orders');
});

/** A query whose `items_total` column is a withSum rollup, not a real column. */
function scqRollupQuery(): Builder
{
    return ScqOrder::query()->withSum('items as items_total', 'amount');
}

// ─── Real column at 'query' scope ─────────────────────────────

test('a real column aggregates natively in SQL', function () {
    $result = $this->calc->compute(SummaryType::Sum, 'query', new SummaryTarget('total'), collect(), ScqOrder::query());

    expect($result)->toBe(60);
});

test('a real-column Range renders min and max from SQL', function () {
    $target = new SummaryTarget('total', false, new SummaryFormat(0));

    expect($this->calc->compute(SummaryType::Range, 'query', $target, collect(), ScqOrder::query()))
        ->toBe('10 – 30');
});

test('a closure over a real column receives the plucked SQL values', function () {
    // The custom-callback path at 'query' scope: the closure is handed the
    // column plucked straight from the database, not the in-memory page.
    $result = $this->calc->compute(
        fn ($values, $q) => $values->all(),
        'query',
        new SummaryTarget('total'),
        collect(),
        ScqOrder::query(),
    );

    expect($result)->toBe([10, 20, 30]);
});

// ─── Rollup column at 'query' scope (derived table) ───────────

test('every SQL-native summary aggregates a rollup over a derived table', function () {
    $target = new SummaryTarget('items_total', isAggregate: true);

    // Rollup values are 150, 300, 200.
    expect($this->calc->compute(SummaryType::Sum, 'query', $target, collect(), scqRollupQuery()))->toBe(650)
        ->and($this->calc->compute(SummaryType::Count, 'query', $target, collect(), scqRollupQuery()))->toBe(3)
        ->and($this->calc->compute(SummaryType::DistinctCount, 'query', $target, collect(), scqRollupQuery()))->toBe(3)
        ->and((int) $this->calc->compute(SummaryType::Min, 'query', $target, collect(), scqRollupQuery()))->toBe(150)
        ->and((int) $this->calc->compute(SummaryType::Max, 'query', $target, collect(), scqRollupQuery()))->toBe(300)
        ->and($this->calc->compute(SummaryType::Avg, 'query', $target, collect(), scqRollupQuery()))->toBe(216.67);
});

test('a rollup Range renders min and max over the derived table', function () {
    $target = new SummaryTarget('items_total', isAggregate: true, format: new SummaryFormat(0));

    expect($this->calc->compute(SummaryType::Range, 'query', $target, collect(), scqRollupQuery()))
        ->toBe('150 – 300');
});

test('a rollup Range over an empty set renders the placeholder', function () {
    // No rows behind the derived table → min and max are both null → the same
    // '–' the in-memory empty set produces.
    $target = new SummaryTarget('items_total', isAggregate: true, format: new SummaryFormat(0));
    $empty = scqRollupQuery()->whereRaw('0 = 1');

    expect($this->calc->compute(SummaryType::Range, 'query', $target, collect(), $empty))->toBe('–');
});

test('a non-SQL-native summary of a rollup falls back to PHP over the derived table', function () {
    // Median is not portable across drivers, so it is computed in PHP from the
    // plucked rollup values (150, 200, 300 → 200).
    $target = new SummaryTarget('items_total', isAggregate: true);

    expect($this->calc->compute(SummaryType::Median, 'query', $target, collect(), scqRollupQuery()))->toBe(200.0);
});

test('a closure over a rollup receives the derived-table values', function () {
    $target = new SummaryTarget('items_total', isAggregate: true);

    $result = $this->calc->compute(
        fn ($values, $q) => $values->count(),
        'query',
        $target,
        collect(),
        scqRollupQuery(),
    );

    expect($result)->toBe(3);
});
