<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

/*
 * Seam matrix: every filter type driven through the real query pipeline
 * (TableQueryService::buildQuery → planner/executor/apply → SQL), asserting the
 * rows that survive. Covers the type equivalence class, the ternary three-state,
 * the "empty value is a no-op" class (a common regression), and the interaction
 * with a relation-sort JOIN (the ambiguous-column seam) that summaries/exports
 * also hit.
 */

class FpmCompany extends Model
{
    protected $table = 'fpm_companies';

    protected $guarded = [];

    public $timestamps = false;
}

class FpmUser extends Model
{
    protected $table = 'fpm_users';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = ['active' => 'boolean', 'joined_on' => 'date'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(FpmCompany::class, 'company_id');
    }
}

beforeEach(function () {
    Schema::create('fpm_companies', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('fpm_users', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('age');
        $t->boolean('active')->nullable();
        $t->date('joined_on');
        $t->foreignId('company_id');
    });

    FpmCompany::insert([
        ['id' => 1, 'name' => 'Acme'],
        ['id' => 2, 'name' => 'Evil'],
    ]);
    FpmUser::insert([
        ['name' => 'Alice', 'age' => 30, 'active' => true, 'joined_on' => '2026-01-15', 'company_id' => 1],
        ['name' => 'Bob', 'age' => 25, 'active' => false, 'joined_on' => '2026-03-20', 'company_id' => 2],
        ['name' => 'Charlie', 'age' => 35, 'active' => true, 'joined_on' => '2026-06-10', 'company_id' => 1],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('fpm_users');
    Schema::dropIfExists('fpm_companies');
});

/**
 * @param  array<int, Filter>  $filters
 * @param  array<string, mixed>  $filterValues
 * @return array<int, string>
 */
function fpmFilter(array $filters, array $filterValues): array
{
    $table = Table::make()->model(FpmUser::class)->columns([Column::make('name')])->filters($filters);

    return (new TableQueryService)
        ->buildQuery(baseQuery: FpmUser::query(), table: $table, filterValues: $filterValues)
        ->orderBy('name')
        ->pluck('name')
        ->all();
}

// ─── Type matrix: each filter narrows to the expected rows ───────────────────
// The filter set is a factory closure so each dataset row builds fresh instances.

it('applies each filter type to the right rows', function (Closure $makeFilters, array $values, array $expected) {
    expect(fpmFilter($makeFilters(), $values))->toBe($expected);
})->with([
    'text (partial match)' => [
        fn () => [TextFilter::make('name')],
        ['name' => ['value' => 'li']],
        ['Alice', 'Charlie'],
    ],
    'select single' => [
        fn () => [SelectFilter::make('company_id')->options(['1' => 'Acme', '2' => 'Evil'])],
        ['company_id' => ['value' => 1]],
        ['Alice', 'Charlie'],
    ],
    'select multiple' => [
        fn () => [SelectFilter::make('company_id')->multiple()->options(['1' => 'Acme', '2' => 'Evil'])],
        ['company_id' => ['value' => [1, 2]]],
        ['Alice', 'Bob', 'Charlie'],
    ],
    'number range (min)' => [
        fn () => [NumberRangeFilter::make('age')],
        ['age' => ['min' => 30, 'max' => '']],
        ['Alice', 'Charlie'],
    ],
    'number range (max)' => [
        fn () => [NumberRangeFilter::make('age')],
        ['age' => ['min' => '', 'max' => 30]],
        ['Alice', 'Bob'],
    ],
    'date range' => [
        fn () => [DateFilter::make('joined_on')->range()],
        ['joined_on' => ['from' => '2026-03-01', 'to' => '2026-07-01']],
        ['Bob', 'Charlie'],
    ],
    'custom query callback' => [
        fn () => [Filter::make('min_age')->query(fn ($q, $v) => $q->where('age', '>=', $v))],
        ['min_age' => ['value' => 35]],
        ['Charlie'],
    ],
]);

// ─── Ternary three-state ─────────────────────────────────────────────────────

it('applies the ternary filter across its three states', function () {
    $filters = fn () => [TernaryFilter::make('active')->nullable()];

    expect(fpmFilter($filters(), ['active' => ['value' => true]]))->toBe(['Alice', 'Charlie'])
        ->and(fpmFilter($filters(), ['active' => ['value' => false]]))->toBe(['Bob'])
        // No value → filter is inactive, all rows pass.
        ->and(fpmFilter($filters(), ['active' => ['value' => null]]))->toBe(['Alice', 'Bob', 'Charlie']);
});

// ─── Empty value is a no-op for every filter type ────────────────────────────

it('treats an empty filter value as a no-op', function (Closure $makeFilters, array $values) {
    expect(fpmFilter($makeFilters(), $values))->toBe(['Alice', 'Bob', 'Charlie']);
})->with([
    'text empty' => [fn () => [TextFilter::make('name')], ['name' => ['value' => '']]],
    'select empty' => [fn () => [SelectFilter::make('company_id')], ['company_id' => ['value' => null]]],
    'select empty array' => [fn () => [SelectFilter::make('company_id')->multiple()], ['company_id' => ['value' => []]]],
    'number range empty' => [fn () => [NumberRangeFilter::make('age')], ['age' => ['min' => '', 'max' => '']]],
    'date range empty' => [fn () => [DateFilter::make('joined_on')->range()], ['joined_on' => ['from' => '', 'to' => '']]],
    'missing key' => [fn () => [TextFilter::make('name')], []],
]);

// ─── Filter over a relation-sorted (joined) query is not ambiguous ───────────

it('applies a filter over a relation-joined query without an ambiguous column', function () {
    // Sorting by a relation column adds a LEFT JOIN; filtering must still resolve
    // against the base table rather than throwing an ambiguous-column error.
    $table = Table::make()
        ->model(FpmUser::class)
        ->columns([Column::make('name'), Column::make('company.name')->sortable()])
        ->filters([NumberRangeFilter::make('age')]);

    $names = (new TableQueryService)->buildQuery(
        baseQuery: FpmUser::query(),
        table: $table,
        filterValues: ['age' => ['min' => 30, 'max' => '']],
        sortColumn: 'company.name',
        sortDirection: 'asc',
    )->pluck('name')->all();

    sort($names);

    expect($names)->toBe(['Alice', 'Charlie']);
});
