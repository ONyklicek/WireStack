<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\SummaryType;
use NyonCode\WireTable\Services\SummarySet;

/*
 * "Run these columns over these rows" — the layer between SummaryCalculator
 * (one value) and SummaryRenderer (the markup), which WithTable used to hold
 * inline and had to repeat for the sub-row branch.
 *
 * What is asserted here is the part indirect coverage would miss: that passing a
 * query batches, that not passing one still computes, and that a column with no
 * summary is absent from the map rather than present and empty. The WithTable
 * suites see the rendered totals either way.
 */

class SsRow extends Model
{
    protected $table = 'ss_rows';

    protected $guarded = [];

    public $timestamps = false;
}

function ssSet(): SummarySet
{
    return app(SummarySet::class);
}

beforeEach(function () {
    Schema::dropIfExists('ss_rows');
    Schema::create('ss_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('amount');
    });

    SsRow::insert([
        ['id' => 1, 'name' => 'a', 'amount' => 10],
        ['id' => 2, 'name' => 'b', 'amount' => 20],
        ['id' => 3, 'name' => 'c', 'amount' => 30],
    ]);
});

afterEach(fn () => Schema::dropIfExists('ss_rows'));

it('summarises the rows it is handed', function () {
    $column = Column::make('amount')->summarize(SummaryType::Sum);

    $summaries = ssSet()->build([$column], SsRow::all());

    expect($summaries)->toHaveKey('amount')
        ->and($summaries['amount'][0]['value'])->toBe(60);
});

it('leaves a column with no summary out of the map entirely', function () {
    // Absent, not present-and-empty: the renderer asks whether a key exists.
    $summaries = ssSet()->build([Column::make('name'), Column::make('amount')->summarize(SummaryType::Sum)], SsRow::all());

    expect($summaries)->not->toHaveKey('name')
        ->and($summaries)->toHaveKey('amount');
});

it('summarises an empty set without asking the database', function () {
    $column = Column::make('amount')->summarize(SummaryType::Sum);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $summaries = ssSet()->build([$column], collect());

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($summaries)->toHaveKey('amount');
});

it('batches through SQL when it is given a query, and not when it is not', function () {
    // The whole reason the query is a separate argument. With one, the
    // SQL-native aggregates fold into the batcher; without one, the same
    // columns are computed from the collection and the database is never asked.
    $columns = [
        Column::make('amount')->summarize(SummaryType::Sum),
        Column::make('amount')->summarize(SummaryType::Avg),
    ];

    // Materialised first: SsRow::all() is itself a query, and counting it would
    // measure the test rather than the code.
    $rows = SsRow::all();

    DB::enableQueryLog();
    DB::flushQueryLog();
    ssSet()->build($columns, collect(), SsRow::query());
    $withQuery = count(DB::getQueryLog());

    DB::flushQueryLog();
    ssSet()->build($columns, $rows);
    $withoutQuery = count(DB::getQueryLog());

    expect($withQuery)->toBeGreaterThan(0)
        ->and($withoutQuery)->toBe(0);
});

it('totals the whole filtered set through the query, not the rows in hand', function () {
    // A query-scope total is over everything the filter matches, which is not
    // what a page of rows would add up to. Handing it an empty collection and a
    // full query is the shape WithTable uses for exactly that.
    $column = Column::make('amount')->summarize(SummaryType::Sum);

    $summaries = ssSet()->build([$column], collect(), SsRow::query()->where('amount', '>', 10));

    expect($summaries['amount'][0]['value'])->toBe(50);
});
