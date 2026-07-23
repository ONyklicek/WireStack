<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * The interactive sub-row filter bar must write to rows.subRowFilters, not the
 * parent table's columnFilters.
 *
 * The bug it covers was invisible to every server-side test, because those set
 * rows.subRowFilters by hand — exactly the state the broken UI never produced.
 * The value only lands in the wrong place when a real control's wire:model
 * decides where a typed value goes, so these assert the rendered binding as well
 * as the resulting query.
 */
class SrfbInvoice extends Model
{
    protected $table = 'srfb_invoices';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<SrfbItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SrfbItem::class, 'invoice_id');
    }
}

class SrfbItem extends Model
{
    protected $table = 'srfb_items';

    protected $guarded = [];

    public $timestamps = false;
}

class SrfbComponent extends Component
{
    use WithTable;

    public bool $filterable = true;

    public bool $multiSelect = false;

    public function table(Table $table): Table
    {
        $product = Column::make('product');
        $category = Column::make('category');

        if ($this->filterable) {
            $product->filterable();
            $this->multiSelect
                ? $category->filterAsMultiSelect(['tools' => 'Tools', 'office' => 'Office'])
                : $category->filterAsSelect(['tools' => 'Tools', 'office' => 'Office']);
        }

        $table = $table
            ->model(SrfbInvoice::class)
            ->paginated(false)
            ->columns([
                Column::make('number'),
                // A parent column of the *same name* as a sub-row column: the old
                // binding wrote the sub-row value here, filtering the parents.
                Column::make('product')->filterable(),
            ])
            ->subRows('items')
            ->subRowColumns([$product, $category]);

        if ($this->filterable) {
            $table->subRowsFilterable();
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('srfb_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('product'); // parent column, deliberately shares the name
    });

    Schema::create('srfb_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('product');
        $table->string('category');
    });

    $inv = SrfbInvoice::create(['number' => 'INV-1', 'product' => 'bundle']);
    SrfbItem::create(['invoice_id' => $inv->id, 'product' => 'apple', 'category' => 'office']);
    SrfbItem::create(['invoice_id' => $inv->id, 'product' => 'banana', 'category' => 'tools']);
    SrfbItem::create(['invoice_id' => $inv->id, 'product' => 'avocado', 'category' => 'office']);
});

afterEach(function () {
    Schema::dropIfExists('srfb_items');
    Schema::dropIfExists('srfb_invoices');
});

// ─── The binding itself ──────────────────────────────────────────────────────

it('binds the sub-row filter input to the sub-row slot, not columnFilters', function () {
    $html = Livewire::test(SrfbComponent::class)
        ->call('toggleRowExpansion', '1')
        ->html();

    // The input the user types into writes to rows.subRowFilters.product.
    expect($html)->toContain('wire:model.live.debounce.300ms="tableState.rows.subRowFilters.product"');

    // The parent header keeps its own product filter — exactly one binding to
    // columnFilters.product, in the header row, never a second one leaked in by
    // the sub-row bar (which is what the bug did).
    expect(substr_count($html, 'tableState.columnFilters.product'))->toBe(1);
});

it('seeds a slot for every filterable sub-row column at mount', function () {
    $component = new SrfbComponent;
    $component->multiSelect = true;
    $component->mountWithTable();

    // Without the seed a select control's entangle is a silent no-op.
    expect($component->tableState->get('rows.subRowFilters.product'))->toBeNull()
        ->and($component->tableState->get('rows.subRowFilters.category'))->toBe([]);
});

it('seeds nothing when the table is not sub-row filterable', function () {
    $component = new SrfbComponent;
    $component->filterable = false;
    $component->mountWithTable();

    expect($component->tableState->get('rows.subRowFilters'))->toBe([]);
});

// ─── The behaviour the binding unlocks ───────────────────────────────────────

it('narrows the children to those matching the sub-row filter', function () {
    $component = new SrfbComponent;
    $component->mountWithTable();
    $component->tableState->set('rows.subRowFilters', ['product' => 'a']);

    $products = $component->getSubRows(SrfbInvoice::first())->pluck('product')->all();

    // apple + avocado contain "a", banana does too — all three actually. Use a
    // sharper term.
    $component->tableState->set('rows.subRowFilters', ['product' => 'av']);
    $products = $component->getSubRows(SrfbInvoice::first())->pluck('product')->all();

    expect($products)->toBe(['avocado']);
});

it('does not touch the parent rows when a sub-row filter is active', function () {
    $component = new SrfbComponent;
    $component->mountWithTable();
    $component->tableState->set('rows.subRowFilters', ['product' => 'zzz']);

    // The one parent survives even though no child matches — a sub-row filter
    // narrows children, never parents.
    expect($component->getTableRecords())->toHaveCount(1);
});

it('disables the eager-load fast path once a sub-row filter is active', function () {
    $component = new SrfbComponent;
    $component->mountWithTable();

    // No filter: the seeded null slot must not count as active, or eager loading
    // would be needlessly skipped on every render.
    expect($component->getSubRows(SrfbInvoice::first()))->toHaveCount(3);

    $component->tableState->set('rows.subRowFilters', ['product' => 'av']);
    expect($component->getSubRows(SrfbInvoice::first()))->toHaveCount(1);
});

// ─── Reset ───────────────────────────────────────────────────────────────────

it('shows the reset control only when a real value is set, not the seeded slot', function () {
    $test = Livewire::test(SrfbComponent::class)->call('toggleRowExpansion', '1');

    // Seeded but empty: no reset.
    $test->assertDontSee('subrows-reset-filters', escape: false);

    $test->set('tableState.rows.subRowFilters.product', 'ap')
        ->assertSee('subrows-reset-filters', escape: false);
});

it('resets by clearing each slot, not by dropping the keys', function () {
    $component = new SrfbComponent;
    $component->multiSelect = true;
    $component->mountWithTable();
    $component->tableState->set('rows.subRowFilters', ['product' => 'ap', 'category' => ['office']]);

    $component->resetSubRowFilters();

    // Cleared to empty values, but the keys survive so the controls keep their
    // entangled paths.
    expect($component->tableState->get('rows.subRowFilters'))->toBe([
        'product' => null,
        'category' => [],
    ]);
});

it('resets to a plain empty array when not sub-row filterable', function () {
    $component = new SrfbComponent;
    $component->filterable = false;
    $component->mountWithTable();

    $component->resetSubRowFilters();

    expect($component->tableState->get('rows.subRowFilters'))->toBe([]);
});
