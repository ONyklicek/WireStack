<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Filters\TextFilter;
use NyonCode\WireTable\Services\SubRowFilters;
use NyonCode\WireTable\Table;

/*
 * Two different things narrow a table's children — a scoped main filter
 * (Filter::subRows()) and the interactive sub-row bar — and the rules used to
 * live in WithTable, reachable only through a Livewire host.
 */

class SrfInvoice extends Model
{
    protected $table = 'srf_invoices';

    protected $guarded = [];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(SrfItem::class, 'invoice_id');
    }
}

class SrfItem extends Model
{
    protected $table = 'srf_items';

    protected $guarded = [];

    public $timestamps = false;
}

function srfTable(array $filters = []): Table
{
    return Table::make()
        ->model(SrfInvoice::class)
        ->columns([Column::make('number')])
        ->filters($filters)
        ->subRows('items')
        ->subRowColumns([Column::make('product')->filterable(), Column::make('amount')])
        ->subRowsFilterable();
}

beforeEach(function () {
    Schema::create('srf_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
    });
    Schema::create('srf_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('invoice_id');
        $t->string('product');
        $t->integer('amount');
        $t->boolean('paid');
    });

    SrfInvoice::insert([['number' => 'A']]);
    SrfItem::insert([
        ['invoice_id' => 1, 'product' => 'Bolt', 'amount' => 10, 'paid' => true],
        ['invoice_id' => 1, 'product' => 'Nut', 'amount' => 20, 'paid' => false],
    ]);

    $this->filters = new SubRowFilters;
});

test('only a subRows()-scoped, viewable filter with a value counts as active', function () {
    $scoped = TextFilter::make('product')->subRows();
    $notScoped = TextFilter::make('number');

    $table = srfTable([$scoped, $notScoped]);

    expect($this->filters->activeScoped($table, ['product' => 'Bolt', 'number' => 'A']))->toHaveCount(1)
        ->and($this->filters->activeScoped($table, ['product' => '']))->toBeEmpty()
        ->and($this->filters->activeScoped($table, []))->toBeEmpty();
});

test('a filter the user cannot view never constrains the children', function () {
    $hidden = TextFilter::make('product')->subRows()->visible(false);

    expect($this->filters->activeScoped(srfTable([$hidden]), ['product' => 'Bolt']))->toBeEmpty();
});

test('a scoped filter narrows the child query', function () {
    $table = srfTable([TextFilter::make('product')->subRows()]);

    $query = $this->filters->applyScoped(SrfItem::query(), $table, ['product' => 'Bolt']);

    expect($query->count())->toBe(1);
});

test('nothing is scoped when sub-rows are not relation-backed', function () {
    $detailRows = Table::make()->model(SrfInvoice::class)->columns([Column::make('number')])
        ->filters([TextFilter::make('product')->subRows()]);

    expect($this->filters->activeScoped($detailRows, ['product' => 'Bolt']))->toBeEmpty();
});

test('the interactive bar narrows the child query per column', function () {
    $query = $this->filters->applyInteractive(SrfItem::query(), srfTable(), ['product' => 'Nut']);

    expect($query->count())->toBe(1);
});

test('an active interactive filter is what disables the eager-load fast path', function () {
    // If this answers false while a filter is set, getSubRows() reads the
    // eager-loaded — unfiltered — children out of memory and shows too many.
    $table = srfTable();

    expect($this->filters->hasActiveInteractive($table, ['product' => 'Nut']))->toBeTrue()
        ->and($this->filters->hasActiveInteractive($table, ['product' => '']))->toBeFalse()
        ->and($this->filters->hasActiveInteractive($table, ['product' => null]))->toBeFalse()
        ->and($this->filters->hasActiveInteractive($table, []))->toBeFalse();
});

test('a table without subRowsFilterable() has no active interactive filters', function () {
    $table = Table::make()->model(SrfInvoice::class)->columns([Column::make('number')])->subRows('items');

    expect($this->filters->hasActiveInteractive($table, ['product' => 'Nut']))->toBeFalse();
});

test('a select filter extracts its value before being applied', function () {
    $table = srfTable([SelectFilter::make('product')->subRows()->options(['Bolt' => 'Bolt'])]);

    expect($this->filters->activeScoped($table, ['product' => 'Bolt']))->toHaveCount(1);
});
