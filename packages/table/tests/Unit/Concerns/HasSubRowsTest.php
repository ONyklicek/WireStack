<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Table;

// ─── Test Models ─────────────────────────────────────────────────────────────

class SrInvoice extends Model
{
    protected $table = 'sr_invoices';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(SrItem::class, 'invoice_id');
    }
}

class SrItem extends Model
{
    protected $table = 'sr_items';

    protected $guarded = [];
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('sr_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->timestamps();
    });

    Schema::create('sr_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('product');
        $table->integer('price');
        $table->timestamps();
    });

    SrInvoice::create(['id' => 1, 'number' => 'INV-1']);

    SrItem::create(['invoice_id' => 1, 'product' => 'Charlie', 'price' => 30]);
    SrItem::create(['invoice_id' => 1, 'product' => 'Alice', 'price' => 10]);
    SrItem::create(['invoice_id' => 1, 'product' => 'Bob', 'price' => 20]);
    SrItem::create(['invoice_id' => 1, 'product' => 'Dave', 'price' => 40]);
});

afterEach(function () {
    Schema::dropIfExists('sr_items');
    Schema::dropIfExists('sr_invoices');
});

function subRowTable(): Table
{
    return Table::make()
        ->model(SrInvoice::class)
        ->subRows('items')
        ->subRowColumns([
            Column::make('product'),
            Column::make('price'),
        ]);
}

// ─── Fluent API ──────────────────────────────────────────────────────────────

it('configures sub-rows via relation', function () {
    $table = subRowTable();

    expect($table->hasSubRows())->toBeTrue()
        ->and($table->getSubRowRelation())->toBe('items')
        ->and($table->getSubRowColumns())->toHaveCount(2);
});

it('is not sortable by default', function () {
    expect(subRowTable()->isSubRowsSortable())->toBeFalse();
});

it('can enable sortable sub-rows with a default sort', function () {
    $table = subRowTable()->subRowsSortable(default: 'price', direction: 'desc');

    expect($table->isSubRowsSortable())->toBeTrue()
        ->and($table->getSubRowsDefaultSort())->toBe('price')
        ->and($table->getSubRowsDefaultSortDirection())->toBe('desc');
});

it('normalises an invalid sort direction to asc', function () {
    $table = subRowTable()->subRowsSortable(default: 'price', direction: 'sideways');

    expect($table->getSubRowsDefaultSortDirection())->toBe('asc');
});

// ─── Sortable guard ──────────────────────────────────────────────────────────

it('rejects sorting by columns when sorting is disabled', function () {
    expect(subRowTable()->isSubRowColumnSortable('price'))->toBeFalse();
});

it('allows sorting only by known sub-row columns when enabled', function () {
    $table = subRowTable()->subRowsSortable();

    expect($table->isSubRowColumnSortable('price'))->toBeTrue()
        ->and($table->isSubRowColumnSortable('product'))->toBeTrue()
        ->and($table->isSubRowColumnSortable('id; DROP TABLE'))->toBeFalse()
        ->and($table->isSubRowColumnSortable('unknown'))->toBeFalse();
});

// ─── Query building: sort ────────────────────────────────────────────────────

it('applies an explicit sort to the sub-rows query', function () {
    $table = subRowTable()->subRowsSortable();
    $invoice = SrInvoice::find(1);

    $prices = $table->getSubRowsQuery($invoice, ['column' => 'price', 'direction' => 'desc'])
        ->pluck('price')->all();

    expect($prices)->toBe([40, 30, 20, 10]);
});

it('applies the configured default sort when no explicit sort is given', function () {
    $table = subRowTable()->subRowsSortable(default: 'product', direction: 'asc');
    $invoice = SrInvoice::find(1);

    $products = $table->getSubRowsQuery($invoice)->pluck('product')->all();

    expect($products)->toBe(['Alice', 'Bob', 'Charlie', 'Dave']);
});

it('ignores a sort on a non-sortable column', function () {
    // sortable not enabled, no default → original insertion order preserved
    $table = subRowTable();
    $invoice = SrInvoice::find(1);

    $products = $table->getSubRowsQuery($invoice, ['column' => 'price', 'direction' => 'desc'])
        ->pluck('product')->all();

    expect($products)->toBe(['Charlie', 'Alice', 'Bob', 'Dave']);
});

// ─── Query building: limit / show-more ───────────────────────────────────────

it('limits sub-rows when a limit is configured', function () {
    $table = subRowTable()->subRowsLimit(2)->subRowsSortable(default: 'price');
    $invoice = SrInvoice::find(1);

    expect($table->getSubRowsQuery($invoice)->get())->toHaveCount(2);
});

it('skips the limit when applyLimit is false', function () {
    $table = subRowTable()->subRowsLimit(2)->subRowsSortable(default: 'price');
    $invoice = SrInvoice::find(1);

    expect($table->getSubRowsQuery($invoice, null, applyLimit: false)->get())->toHaveCount(4);
});

// ─── Sub-row actions ─────────────────────────────────────────────────────────

it('has no sub-row actions by default', function () {
    expect(subRowTable()->hasSubRowActions())->toBeFalse();
});

it('can register sub-row actions', function () {
    $action = new stdClass;
    $table = subRowTable()->subRowActions([$action]);

    expect($table->hasSubRowActions())->toBeTrue()
        ->and($table->getSubRowActions())->toBe([$action]);
});

// ─── Detail-row mode (custom view) ───────────────────────────────────────────

it('enables sub-rows when only a custom view is set, with no relation', function () {
    $table = Table::make()->model(SrInvoice::class)->subRowView('components.detail');

    expect($table->hasSubRows())->toBeTrue()
        ->and($table->getSubRowRelation())->toBeNull()
        ->and($table->getSubRowView())->toBe('components.detail');
});

// ─── Flatten config ──────────────────────────────────────────────────────────

it('is not flattened by default', function () {
    expect(subRowTable()->isFlattenSubRows())->toBeFalse();
});

it('can flatten sub-rows via config', function () {
    expect(subRowTable()->flattenSubRows()->isFlattenSubRows())->toBeTrue();
});
