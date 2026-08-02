<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Exceptions\TableHasNoDataSourceException;
use NyonCode\WireTable\Table;

/*
 * Where a table's rows come from, before anything narrows them. The other half
 * — search, filters, sorting, joins — belongs to TableQueryService; nothing
 * here has seen a request.
 */

class HdsOrder extends Model
{
    protected $table = 'hds_orders';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('hds_orders', function (Blueprint $t) {
        $t->id();
        $t->string('reference');
        $t->boolean('active');
    });

    HdsOrder::insert([
        ['reference' => 'A-1', 'active' => true],
        ['reference' => 'A-2', 'active' => false],
    ]);
});

it('builds the base query from a model', function () {
    $table = Table::make()->model(HdsOrder::class);

    expect($table->getModelClass())->toBe(HdsOrder::class)
        ->and($table->getQuery()->count())->toBe(2);
});

it('builds it from a prepared builder instead', function () {
    $table = Table::make()->query(HdsOrder::query()->where('active', true));

    expect($table->getModelClass())->toBeNull()
        ->and($table->getQuery()->count())->toBe(1);
});

it('lets a prepared builder win over a model', function () {
    $table = Table::make()
        ->model(HdsOrder::class)
        ->query(HdsOrder::query()->where('active', true));

    expect($table->getQuery()->count())->toBe(1);
});

it('refuses to invent an empty result when there is no source', function () {
    Table::make()->getQuery();
})->throws(TableHasNoDataSourceException::class);

it('hands out a clone, so constraints never accumulate on the table', function () {
    $table = Table::make()->query(HdsOrder::query());

    // Without the clone, this where() would narrow every later call.
    $table->getQuery()->where('active', true);

    expect($table->getQuery()->count())->toBe(2);
});

it('applies the caller\'s modification to the base query', function () {
    $table = Table::make()
        ->model(HdsOrder::class)
        ->modifyQueryUsing(fn ($query) => $query->where('active', true));

    expect($table->getQuery()->count())->toBe(1)
        ->and($table->getModifyQueryCallback())->toBeInstanceOf(Closure::class);
});

it('accepts a modification that mutates in place and returns nothing', function () {
    $table = Table::make()
        ->model(HdsOrder::class)
        ->modifyQueryUsing(function ($query) {
            $query->where('active', true);
        });

    expect($table->getQuery()->count())->toBe(1);
});
