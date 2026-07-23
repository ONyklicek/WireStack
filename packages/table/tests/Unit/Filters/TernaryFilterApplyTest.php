<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests\Unit\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Services\TableQueryService;
use NyonCode\WireTable\Table;

class TfUser extends Model
{
    protected $table = 'tf_users';

    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}

beforeEach(function () {
    Schema::create('tf_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->boolean('active')->nullable();
        $table->timestamps();
    });

    TfUser::create(['id' => 1, 'name' => 'Alice', 'active' => true]);
    TfUser::create(['id' => 2, 'name' => 'Bob', 'active' => false]);
    TfUser::create(['id' => 3, 'name' => 'Carol', 'active' => null]);
});

afterEach(function () {
    Schema::dropIfExists('tf_users');
});

function tfRun(string $value, ?TernaryFilter $filter = null): Collection
{
    $filter ??= TernaryFilter::make('active');

    $table = Table::make()
        ->model(TfUser::class)
        ->filters([$filter]);

    $service = new TableQueryService;

    return $service->buildQuery(
        baseQuery: TfUser::query(),
        table: $table,
        filterValues: [$filter->getName() => ['value' => $value]],
    )->get();
}

it('filters to truthy rows when value is "true" (UI value)', function () {
    $rows = tfRun('true');
    expect($rows->pluck('name')->all())->toBe(['Alice']);
});

it('filters to falsy rows when value is "false" (UI value)', function () {
    $rows = tfRun('false');
    expect($rows->pluck('name')->all())->toBe(['Bob']);
});

it('treats NULL as false when nullable()', function () {
    $rows = tfRun('false', TernaryFilter::make('active')->nullable());
    expect($rows->pluck('name')->all())->toBe(['Bob', 'Carol']);
});

it('returns all rows when value is empty (inactive)', function () {
    $rows = tfRun('');
    expect($rows->pluck('name')->all())->toBe(['Alice', 'Bob', 'Carol']);
});

/*
 * A ->query() callback used to receive the raw select state, so the natural
 * `$value ? A : B` branched on the string 'false' — truthy in PHP — and both
 * options produced the same rows. The value is normalized to a real bool before
 * the callback runs, on every path that reaches it.
 */
it('hands the query callback a real bool, not the raw select state', function () {
    $seen = [];

    $filter = TernaryFilter::make('active')
        ->query(function (Builder $query, $value) use (&$seen) {
            $seen[] = $value;

            return $value
                ? $query->where('name', 'Alice')
                : $query->where('name', 'Bob');
        });

    $trueRows = tfRun('true', $filter);
    $falseRows = tfRun('false', $filter);

    expect($seen)->toBe([true, false])
        ->and($trueRows->pluck('name')->all())->toBe(['Alice'])
        ->and($falseRows->pluck('name')->all())->toBe(['Bob']);
});

it('hands the query callback a real bool through the column header filter too', function () {
    $seen = [];

    $column = TextColumn::make('active')
        ->filterAsBoolean()
        ->filterUsing(function (Builder $query, $value) use (&$seen) {
            $seen[] = $value;

            return $query->where('name', $value ? 'Alice' : 'Bob');
        });

    $table = Table::make()->model(TfUser::class)->columns([$column]);

    $rows = (new TableQueryService)->buildQuery(
        baseQuery: TfUser::query(),
        table: $table,
        columnFilterValues: ['active' => 'false'],
    )->get();

    expect($seen)->toBe([false])
        ->and($rows->pluck('name')->all())->toBe(['Bob']);
});

it('passes the raw submitted state to the callback as a third argument', function () {
    $seen = [];

    $filter = TernaryFilter::make('active')
        ->query(function (Builder $query, $value, $rawValue) use (&$seen) {
            $seen = ['normalized' => $value, 'raw' => $rawValue];

            return $query;
        });

    tfRun('false', $filter);

    expect($seen)->toBe(['normalized' => false, 'raw' => 'false']);
});

it('does not call the query callback while the filter is inactive', function () {
    $calls = 0;

    $filter = TernaryFilter::make('active')
        ->query(function (Builder $query) use (&$calls) {
            $calls++;

            return $query;
        });

    $rows = tfRun('', $filter);

    expect($calls)->toBe(0)
        ->and($rows->pluck('name')->all())->toBe(['Alice', 'Bob', 'Carol']);
});

/*
 * nullable() expands the "false" branch of the DEFAULT query. A ->query()
 * callback owns its own query, so it is not applied there — the callback is
 * told which side was picked and decides how NULL should behave.
 */
it('leaves the nullable() expansion to the callback when one is set', function () {
    $filter = TernaryFilter::make('active')
        ->nullable()
        ->query(fn (Builder $query, $value) => $query->where('active', $value));

    $rows = tfRun('false', $filter);

    expect($rows->pluck('name')->all())->toBe(['Bob']);
});

it('normalizes every accepted transport form to a bool', function (mixed $input, ?bool $expected) {
    expect(TernaryFilter::make('active')->normalizeValue($input))->toBe($expected);
})->with([
    'option key true' => ['true', true],
    'option key false' => ['false', false],
    'string one' => ['1', true],
    'string zero' => ['0', false],
    'int one' => [1, true],
    'int zero' => [0, false],
    'bool true' => [true, true],
    'bool false' => [false, false],
    'null (all)' => [null, null],
    'empty string (all)' => ['', null],
    'unknown state' => ['maybe', null],
]);
