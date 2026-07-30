<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
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

// ─── Audit follow-ups ────────────────────────────────────────────────────────

it('throws a clear exception when the sub-row relation is misspelled', function () {
    // Not a bare "Call to undefined method Invoice::itemz()" that never mentions subRows().
    $table = Table::make()->model(SrInvoice::class)->subRows('itemz')->subRowColumns([Column::make('product')]);

    expect(fn () => $table->getSubRowsQuery(SrInvoice::first()))
        ->toThrow(
            TableConfigurationException::class,
            "subRows('itemz')",
        );
});

it('still allows ordering by the configured default column when headers are not clickable', function () {
    // isSubRowColumnSortable() must stay lenient toward the default column so the
    // configured default sort applies even when the table is not interactively
    // sortable — this is the leniency sortSubRows() must NOT ride (see the
    // CanExpandSubRows test).
    $table = subRowTable()->subRowsSortable(sortable: false, default: 'price', direction: 'asc');

    expect($table->isSubRowsSortable())->toBeFalse()
        ->and($table->isSubRowColumnSortable('price'))->toBeTrue()
        ->and($table->isSubRowColumnSortable('product'))->toBeFalse();

    // And the query actually orders by it.
    $prices = $table->getSubRowsQuery(SrInvoice::first())->pluck('price')->all();
    expect($prices)->toBe([10, 20, 30, 40]);
});

// ─── Per-record visibility ───────────────────────────────────────────────────

it('gives every record sub-rows by default', function () {
    expect(subRowTable()->hasSubRowsFor(SrInvoice::first()))->toBeTrue();
});

it('denies sub-rows to a record the condition rejects', function () {
    $table = subRowTable()->subRowsVisible(fn (SrInvoice $record) => $record->number === 'INV-2');

    expect($table->hasSubRowsFor(SrInvoice::first()))->toBeFalse();
});

it('accepts a plain bool as the per-record condition', function () {
    expect(subRowTable()->subRowsVisible(false)->hasSubRowsFor(SrInvoice::first()))->toBeFalse()
        ->and(subRowTable()->subRowsVisible()->hasSubRowsFor(SrInvoice::first()))->toBeTrue();
});

it('reports no sub-rows for a record when the table has none at all', function () {
    // The structural check comes first: a condition returning true cannot
    // conjure an expander column the table never configured.
    $table = Table::make()->model(SrInvoice::class)->subRowsVisible(true);

    expect($table->hasSubRowsFor(SrInvoice::first()))->toBeFalse();
});

it('evaluates the condition once per record, not once per caller', function () {
    // Both layouts are in the document at every width, so the chevron, the panel
    // and the eager load all ask the same question — a condition touching the
    // database would otherwise be N queries several times over.
    $calls = 0;
    $table = subRowTable()->subRowsVisible(function () use (&$calls) {
        $calls++;

        return true;
    });

    $table->hasSubRowsFor(SrInvoice::first());
    $table->hasSubRowsFor(SrInvoice::first());   // a re-fetched record, same key
    $table->hasSubRowsFor(SrInvoice::find(1));

    expect($calls)->toBe(1);
});

it('drops the memoized results when the condition is replaced', function () {
    $table = subRowTable()->subRowsVisible(fn () => true);
    expect($table->hasSubRowsFor(SrInvoice::first()))->toBeTrue();

    $table->subRowsVisible(fn () => false);
    expect($table->hasSubRowsFor(SrInvoice::first()))->toBeFalse();
});

it('memoizes an unsaved record by identity, not by a null key', function () {
    // Two unsaved records share a null key; keying the cache on it would make
    // the first answer stand in for every other new record.
    $calls = 0;
    $table = subRowTable()->subRowsVisible(function (SrInvoice $record) use (&$calls) {
        $calls++;

        return $record->number === 'kept';
    });

    $kept = new SrInvoice(['number' => 'kept']);
    $dropped = new SrInvoice(['number' => 'other']);

    expect($table->hasSubRowsFor($kept))->toBeTrue()
        ->and($table->hasSubRowsFor($dropped))->toBeFalse()
        ->and($table->hasSubRowsFor($kept))->toBeTrue()
        ->and($calls)->toBe(2);
});
