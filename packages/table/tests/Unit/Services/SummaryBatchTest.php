<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Services\SummaryBatch;

/*
 * SummaryBatch exists for exactly one reason: one aggregate query instead of one
 * per summary per column. Nothing asserted that. Its 88 statements were covered
 * indirectly through the WithTable suites, so breaking the batching — falling
 * back to the per-summary path — would leave every test green and only make the
 * footer quietly slower. These tests count the queries.
 */

class SbInvoice extends Model
{
    protected $table = 'sb_invoices';

    protected $guarded = [];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(SbItem::class, 'invoice_id');
    }
}

class SbItem extends Model
{
    protected $table = 'sb_items';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('sb_invoices', function (Blueprint $t) {
        $t->id();
        $t->integer('total');
        $t->integer('qty');
    });
    Schema::create('sb_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('invoice_id');
        $t->integer('amount');
    });

    SbInvoice::insert([
        ['total' => 100, 'qty' => 1],
        ['total' => 300, 'qty' => 3],
        ['total' => 200, 'qty' => 2],
    ]);
    SbItem::insert([
        ['invoice_id' => 1, 'amount' => 40],
        ['invoice_id' => 1, 'amount' => 60],
        ['invoice_id' => 2, 'amount' => 300],
        ['invoice_id' => 3, 'amount' => 200],
    ]);
});

test('six summaries across two columns cost one query', function () {
    $columns = [
        Column::make('total')->summarizeSum()->summarizeAvg()->summarizeMin()->summarizeMax(),
        Column::make('qty')->summarizeSum()->summarizeCount(),
    ];

    DB::enableQueryLog();
    $results = app(SummaryBatch::class)->compute($columns, SbInvoice::query());

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($results['total'][0])->toBe(600)   // sum
        ->and($results['total'][1])->toBe(200.0) // avg
        ->and($results['total'][2])->toBe(100)   // min
        ->and($results['total'][3])->toBe(300)   // max
        ->and($results['qty'][0])->toBe(6)
        ->and($results['qty'][1])->toBe(3);      // count
});

test('qualifies the summarized column so a relation-joined query is not ambiguous', function () {
    // Regression: a relation sort adds a LEFT JOIN, and `id` (present on both
    // tables) made SUM/COUNT ambiguous — the aggregate threw and took down the
    // whole table render. The column must be qualified to the base table.
    $columns = [Column::make('id')->summarizeCount('Count')];

    $joined = SbInvoice::query()
        ->leftJoin('sb_items', 'sb_items.invoice_id', '=', 'sb_invoices.id');

    $results = app(SummaryBatch::class)->compute($columns, $joined);

    // 4 joined rows (invoice 1 has two items); the point is it does not throw
    // "ambiguous column: id".
    expect($results['id'][0])->toBe(4);
});

test('no SQL-native summary type is ambiguous over a relation-joined query', function (string $method) {
    // Seam matrix: every batchable aggregate over a column shared by the base and
    // the joined table (`id`) must qualify to the base table and not throw
    // "ambiguous column" — the class of bug that took down the whole footer/render
    // once a relation column was sorted.
    $column = Column::make('id')->{$method}('Agg');

    $joined = SbInvoice::query()
        ->leftJoin('sb_items', 'sb_items.invoice_id', '=', 'sb_invoices.id');

    $results = app(SummaryBatch::class)->compute([$column], $joined);

    // The point is it computed a value instead of throwing.
    expect($results['id'][0])->not->toBeNull();
})->with([
    'summarizeSum',
    'summarizeAvg',
    'summarizeMin',
    'summarizeMax',
    'summarizeCount',
    'summarizeDistinct',
]);

test('a rollup column aggregates over the derived table', function () {
    // The path that wraps the query with fromSub so a withSum alias is
    // addressable — it delegates to SummaryCalculator::wrap().
    $column = Column::make('items_sum_amount')->sums('items', 'amount')->summarizeSum();

    $results = app(SummaryBatch::class)->compute(
        [$column],
        SbInvoice::query()->withSum('items', 'amount'),
    );

    expect($results['items_sum_amount'][0])->toBe(600);
});

test('plain and rollup columns cost one query each, not one per summary', function () {
    $columns = [
        Column::make('total')->summarizeSum()->summarizeMax(),
        Column::make('items_sum_amount')->sums('items', 'amount')->summarizeSum()->summarizeMax(),
    ];

    DB::enableQueryLog();
    app(SummaryBatch::class)->compute($columns, SbInvoice::query()->withSum('items', 'amount'));

    // One for the base table, one for the derived table. Not four.
    expect(DB::getQueryLog())->toHaveCount(2);
});

test('what cannot be batched is skipped rather than mis-aggregated', function () {
    $columns = [
        // A non-SQL-native type, a when() restriction and a closure each fall
        // back to the per-summary path.
        Column::make('total')->summarizeMedian(),
        Column::make('qty')->summarize('sum', when: fn ($q) => $q->where('qty', '>', 1)),
        Column::make('total')->summarize(fn ($values) => $values->count()),
        // A relation path cannot be aggregated on the base table.
        Column::make('customer.name')->summarizeCount(),
    ];

    DB::enableQueryLog();
    $results = app(SummaryBatch::class)->compute($columns, SbInvoice::query());

    expect(DB::getQueryLog())->toHaveCount(0)
        ->and($results)->toBe([]);
});
