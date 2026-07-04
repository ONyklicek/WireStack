<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireCore\Infolists\Components\RepeatableEntry;
use NyonCode\WireCore\Infolists\Components\TextEntry;

/**
 * N+1 hardening: RepeatableEntry::with() must eager-load the declared relations
 * across every model row in a single query, so child entries reading a nested
 * relation path (e.g. `product.name`) do not lazy-load once per row.
 */
class EagerLine extends Model
{
    protected $table = 'el_lines';

    public $timestamps = false;

    protected $guarded = [];

    public function product(): BelongsTo
    {
        return $this->belongsTo(EagerProduct::class, 'product_id');
    }
}

class EagerProduct extends Model
{
    protected $table = 'el_products';

    public $timestamps = false;

    protected $guarded = [];
}

beforeEach(function () {
    Schema::create('el_products', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    Schema::create('el_lines', function (Blueprint $table) {
        $table->id();
        $table->foreignId('product_id');
        $table->string('sku');
    });

    EagerProduct::create(['name' => 'P1']);
    EagerProduct::create(['name' => 'P2']);
    EagerProduct::create(['name' => 'P3']);

    EagerLine::create(['product_id' => 1, 'sku' => 'A']);
    EagerLine::create(['product_id' => 2, 'sku' => 'B']);
    EagerLine::create(['product_id' => 3, 'sku' => 'C']);
});

afterEach(function () {
    Schema::dropIfExists('el_lines');
    Schema::dropIfExists('el_products');
});

it('eager-loads declared relations onto every model row', function () {
    $entry = RepeatableEntry::make('lines')
        ->state(fn () => EagerLine::all())
        ->with('product')
        ->schema([TextEntry::make('product.name')]);

    $items = $entry->getRowItems();

    expect($items)->toHaveCount(3)
        ->and(collect($items)->every(fn (EagerLine $line) => $line->relationLoaded('product')))->toBeTrue();
});

it('does not eager-load relations without with()', function () {
    $entry = RepeatableEntry::make('lines')
        ->state(fn () => EagerLine::all())
        ->schema([TextEntry::make('product.name')]);

    $items = $entry->getRowItems();

    expect(collect($items)->every(fn (EagerLine $line) => ! $line->relationLoaded('product')))->toBeTrue();
});

it('resolves nested relation paths in a single query with with()', function () {
    $lines = EagerLine::all();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $entry = RepeatableEntry::make('lines')
        ->state(fn () => $lines)
        ->with('product')
        ->schema([TextEntry::make('product.name')]);

    $items = $entry->getRowItems();

    foreach ($items as $line) {
        data_get($line, 'product.name');
    }

    // Only the single loadMissing('product') query; no per-row lazy loads.
    expect(DB::getQueryLog())->toHaveCount(1);
});

it('falls into an N+1 pattern without with() (baseline)', function () {
    $lines = EagerLine::all();

    DB::enableQueryLog();
    DB::flushQueryLog();

    $entry = RepeatableEntry::make('lines')
        ->state(fn () => $lines)
        ->schema([TextEntry::make('product.name')]);

    $items = $entry->getRowItems();

    foreach ($items as $line) {
        data_get($line, 'product.name');
    }

    // One lazy load per row: three rows → three queries.
    expect(DB::getQueryLog())->toHaveCount(3);
});
