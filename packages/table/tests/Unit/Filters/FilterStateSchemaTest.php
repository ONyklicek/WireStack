<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\TableQueryService;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Table;

// ─── Test Model ──────────────────────────────────────────────────────────────

class FssRecord extends Model
{
    protected $table = 'fss_records';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('fss_records', function (Blueprint $table) {
        $table->id();
        $table->string('status');
        $table->boolean('active')->default(false);
        $table->integer('amount')->default(0);
        $table->date('published_at')->nullable();
        $table->timestamps();
    });

    FssRecord::create(['status' => 'draft', 'active' => false, 'amount' => 5, 'published_at' => '2024-01-01']);
    FssRecord::create(['status' => 'published', 'active' => true, 'amount' => 25, 'published_at' => '2024-06-15']);
    FssRecord::create(['status' => 'published', 'active' => true, 'amount' => 50, 'published_at' => '2024-12-31']);
});

afterEach(function () {
    Schema::dropIfExists('fss_records');
});

// ─── extractValue / wrapValue contracts ─────────────────────────────────────

it('extracts scalar state via the value key', function () {
    $filter = Filter::make('status');

    expect($filter->extractValue(['value' => 'published']))->toBe('published')
        ->and($filter->extractValue('legacy-scalar'))->toBe('legacy-scalar')
        ->and($filter->extractValue(null))->toBeNull();
});

it('returns the whole array for multi-field filters', function () {
    $filter = NumberRangeFilter::make('amount');

    $value = ['min' => 10, 'max' => 100];
    expect($filter->extractValue($value))->toBe($value);
});

it('wraps scalar defaults into the form-field shape', function () {
    expect(Filter::make('status')->wrapValue('active'))->toBe(['value' => 'active'])
        ->and(SelectFilter::make('status')->wrapValue('active'))->toBe(['value' => 'active'])
        ->and(TernaryFilter::make('active')->wrapValue('1'))->toBe(['value' => '1']);
});

it('does not re-wrap multi-field defaults', function () {
    expect(NumberRangeFilter::make('amount')->wrapValue(['min' => 10, 'max' => 100]))
        ->toBe(['min' => 10, 'max' => 100])
        ->and(DateFilter::make('published_at')->range()->wrapValue(['from' => '2024-01-01', 'to' => '2024-12-31']))
        ->toBe(['from' => '2024-01-01', 'to' => '2024-12-31']);
});

it('preserves already-wrapped values via wrapValue', function () {
    expect(Filter::make('status')->wrapValue(['value' => 'x']))->toBe(['value' => 'x']);
});

// ─── End-to-end query application with new state shape ─────────────────────

it('filters via SelectFilter using the wrapped state shape', function () {
    $table = Table::make()
        ->model(FssRecord::class)
        ->columns([Column::make('status')])
        ->filters([SelectFilter::make('status')->options(['draft' => 'Draft', 'published' => 'Published'])]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: FssRecord::query(),
        table: $table,
        filterValues: ['status' => ['value' => 'published']],
    );

    expect($query->count())->toBe(2);
});

it('filters via TernaryFilter using the wrapped state shape', function () {
    $table = Table::make()
        ->model(FssRecord::class)
        ->columns([Column::make('active')])
        ->filters([TernaryFilter::make('active')]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: FssRecord::query(),
        table: $table,
        filterValues: ['active' => ['value' => '1']],
    );

    expect($query->count())->toBe(2);
});

it('filters via DateFilter range using the keyed state shape', function () {
    $table = Table::make()
        ->model(FssRecord::class)
        ->columns([Column::make('published_at')])
        ->filters([DateFilter::make('published_at')->range()]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: FssRecord::query(),
        table: $table,
        filterValues: ['published_at' => ['from' => '2024-06-01', 'to' => '2024-12-31']],
    );

    expect($query->count())->toBe(2);
});

it('filters via NumberRangeFilter using the keyed state shape', function () {
    $table = Table::make()
        ->model(FssRecord::class)
        ->columns([Column::make('amount')])
        ->filters([NumberRangeFilter::make('amount')]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: FssRecord::query(),
        table: $table,
        filterValues: ['amount' => ['min' => 20, 'max' => 100]],
    );

    expect($query->count())->toBe(2);
});

it('routes custom query callbacks the extracted scalar value', function () {
    $captured = null;

    $table = Table::make()
        ->model(FssRecord::class)
        ->columns([Column::make('amount')])
        ->filters([
            Filter::make('amount_min')
                ->query(function ($query, $value) use (&$captured) {
                    $captured = $value;

                    return $query->where('amount', '>=', $value);
                }),
        ]);

    $service = new TableQueryService;
    $query = $service->buildQuery(
        baseQuery: FssRecord::query(),
        table: $table,
        filterValues: ['amount_min' => ['value' => 25]],
    );

    expect($captured)->toBe(25)
        ->and($query->count())->toBe(2);
});
