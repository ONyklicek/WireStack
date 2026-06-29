<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Tests\Unit\Filters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Concerns\TableQueryService;
use NyonCode\WireTable\Filters\TernaryFilter;
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
    $table = Table::make()
        ->model(TfUser::class)
        ->filters([$filter ?? TernaryFilter::make('active')]);

    $service = new TableQueryService;

    return $service->buildQuery(
        baseQuery: TfUser::query(),
        table: $table,
        filterValues: ['active' => ['value' => $value]],
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
