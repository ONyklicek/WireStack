<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Services\AggregateSubqueries;

/*
 * The five-arm withCount/withSum map, which was written twice: once in
 * TableQueryService and once inline in WithTable::getSelectedRecords(). A missing
 * subquery does not error — the rollup attribute is simply absent and a summary
 * over it renders 0, so both copies had to agree without anything checking.
 */

class AsInvoice extends Model
{
    protected $table = 'as_invoices';

    protected $guarded = [];

    public $timestamps = false;

    public function items()
    {
        return $this->hasMany(AsItem::class, 'invoice_id');
    }
}

class AsItem extends Model
{
    protected $table = 'as_items';

    protected $guarded = [];

    public $timestamps = false;
}

beforeEach(function () {
    Schema::create('as_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
    });
    Schema::create('as_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('invoice_id');
        $t->integer('amount');
        $t->boolean('paid');
    });

    AsInvoice::insert([['number' => 'A'], ['number' => 'B']]);
    AsItem::insert([
        ['invoice_id' => 1, 'amount' => 100, 'paid' => true],
        ['invoice_id' => 1, 'amount' => 50, 'paid' => false],
        ['invoice_id' => 2, 'amount' => 300, 'paid' => true],
    ]);

    $this->applier = new AggregateSubqueries;
});

test('each aggregate function exposes its rollup attribute', function () {
    $columns = [
        Column::make('items_count')->counts('items'),
        Column::make('items_sum_amount')->sums('items', 'amount'),
        Column::make('items_max_amount')->maxes('items', 'amount'),
    ];

    $invoice = $this->applier->apply(AsInvoice::query(), $columns)->find(1);

    expect($invoice->items_count)->toBe(2)
        ->and((int) $invoice->items_sum_amount)->toBe(150)
        ->and((int) $invoice->items_max_amount)->toBe(100);
});

test('a non-aggregate column adds no subquery', function () {
    $invoice = $this->applier->apply(AsInvoice::query(), [Column::make('number')])->find(1);

    expect($invoice->getAttributes())->not->toHaveKey('items_count');
});

test('a sub-row constraint narrows the rollup and keeps the default alias', function () {
    // The alias must survive the constrained array syntax, or the summary plucks
    // a differently-named attribute and silently reads 0.
    $columns = [Column::make('items_sum_amount')->sums('items', 'amount')];

    $invoice = $this->applier
        ->apply(AsInvoice::query(), $columns, 'items', fn ($q) => $q->where('paid', true))
        ->find(1);

    expect((int) $invoice->items_sum_amount)->toBe(100); // 150 unconstrained
});

test('the constraint applies only to the sub-row relation it names', function () {
    $columns = [Column::make('items_sum_amount')->sums('items', 'amount')];

    $invoice = $this->applier
        ->apply(AsInvoice::query(), $columns, 'somethingElse', fn ($q) => $q->where('paid', true))
        ->find(1);

    expect((int) $invoice->items_sum_amount)->toBe(150);
});
