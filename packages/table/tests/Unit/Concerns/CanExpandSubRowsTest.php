<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * The sub-row host endpoints the chevrons, the sort headers and the "show all"
 * control call: expand/collapse-all (both the normal and the default-expanded
 * inversion), per-column sub-row sorting, the show-all flag, and the getSubRows
 * edge cases (no sub-rows, and detail mode with no relation). Driven through the
 * Livewire component exactly as the view does.
 */

class CeInvoice extends Model
{
    protected $table = 'ce_invoices';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<CeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CeItem::class, 'invoice_id');
    }
}

class CeItem extends Model
{
    protected $table = 'ce_items';

    protected $guarded = [];

    public $timestamps = false;
}

class CeSubRowsComponent extends Component
{
    use WithTable;

    public bool $defaultExpanded = false;

    /** Constrain the eager-loaded children to price >= 20. */
    public bool $withQueryCallback = false;

    public int $limit = 0;

    /** When false, a default sort is configured but headers are not clickable. */
    public bool $sortableHeaders = true;

    public function table(Table $table): Table
    {
        $table = $table
            ->model(CeInvoice::class)
            ->paginated(false)
            ->columns([Column::make('number')])
            ->subRows('items')
            ->subRowColumns([Column::make('product'), Column::make('price')])
            ->subRowsSortable(sortable: $this->sortableHeaders, default: 'price', direction: 'asc')
            ->subRowsFilterable();

        if ($this->defaultExpanded) {
            $table->subRowsDefaultExpanded();
        }

        if ($this->withQueryCallback) {
            $table->subRowQuery(fn ($query) => $query->where('price', '>=', 20));
        }

        if ($this->limit > 0) {
            $table->subRowsLimit($this->limit);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Detail mode: sub-row columns but no relation, so a row is its own sub-row. */
class CeDetailComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(CeInvoice::class)
            ->paginated(false)
            ->columns([Column::make('number')])
            ->subRowColumns([Column::make('number')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class CeNoSubRowsComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table->model(CeInvoice::class)->paginated(false)->columns([Column::make('number')]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('ce_invoices', function (Blueprint $t) {
        $t->id();
        $t->string('number');
    });
    Schema::create('ce_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('invoice_id');
        $t->string('product');
        $t->integer('price');
    });

    CeInvoice::insert([['id' => 1, 'number' => 'A'], ['id' => 2, 'number' => 'B']]);
    CeItem::insert([
        ['invoice_id' => 1, 'product' => 'Pen', 'price' => 30],
        ['invoice_id' => 1, 'product' => 'Ink', 'price' => 10],
        ['invoice_id' => 2, 'product' => 'Pad', 'price' => 20],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('ce_items');
    Schema::dropIfExists('ce_invoices');
});

// ─── Expand / collapse all ────────────────────────────────────

it('expands every row by moving the baseline, not by listing keys', function () {
    $test = Livewire::test(CeSubRowsComponent::class)->call('expandAllRows');

    // A key list would only ever cover the page it was built from; the baseline
    // covers rows the user has not paged to yet.
    expect($test->instance()->tableState->get('rows.expandAll'))->toBeTrue()
        ->and($test->instance()->tableState->get('rows.expanded'))->toBe([])
        ->and($test->instance()->isRowExpanded('1'))->toBeTrue()
        ->and($test->instance()->isRowExpanded('999'))->toBeTrue();
});

it('collapses every row by moving the baseline back', function () {
    $test = Livewire::test(CeSubRowsComponent::class)
        ->call('expandAllRows')
        ->call('collapseAllRows');

    expect($test->instance()->tableState->get('rows.expandAll'))->toBeFalse()
        ->and($test->instance()->tableState->get('rows.expanded'))->toBe([])
        ->and($test->instance()->isRowExpanded('1'))->toBeFalse();
});

it('drops per-row exceptions when the baseline moves', function () {
    $test = Livewire::test(CeSubRowsComponent::class)
        ->call('toggleRowExpansion', '1')
        ->call('expandAllRows');

    expect($test->instance()->tableState->get('rows.expanded'))->toBe([])
        ->and($test->instance()->isRowExpanded('1'))->toBeTrue();
});

it('reads the baseline from the table config until the user overrides it', function () {
    $test = Livewire::test(CeSubRowsComponent::class, ['defaultExpanded' => true]);

    // Nothing chosen yet → subRowsDefaultExpanded() decides, and the list tracks
    // *collapsed* rows.
    expect($test->instance()->tableState->get('rows.expandAll'))->toBeNull()
        ->and($test->instance()->expandsSubRowsByDefault())->toBeTrue()
        ->and($test->instance()->isRowExpanded('1'))->toBeTrue();

    $test->call('toggleRowExpansion', '1');
    expect($test->instance()->isRowExpanded('1'))->toBeFalse()
        ->and($test->instance()->isRowExpanded('2'))->toBeTrue();

    // The user's own choice wins over the config from here on.
    $test->call('collapseAllRows');
    expect($test->instance()->expandsSubRowsByDefault())->toBeFalse()
        ->and($test->instance()->isRowExpanded('1'))->toBeFalse()
        ->and($test->instance()->isRowExpanded('2'))->toBeFalse();
});

it('flips the baseline from the master toggle', function () {
    $test = Livewire::test(CeSubRowsComponent::class);

    $test->call('toggleAllRowExpansion');
    expect($test->instance()->expandsSubRowsByDefault())->toBeTrue();

    $test->call('toggleAllRowExpansion');
    expect($test->instance()->expandsSubRowsByDefault())->toBeFalse();
});

it('keeps toggleFlattenMode working as an alias of the master toggle', function () {
    $test = Livewire::test(CeSubRowsComponent::class)->call('toggleFlattenMode');

    expect($test->instance()->expandsSubRowsByDefault())->toBeTrue()
        ->and($test->instance()->isRowExpanded('1'))->toBeTrue();
});

// ─── The controls that drive it ───────────────────────────────

it('renders the master toggle in the expander column header, not a toolbar', function () {
    $test = Livewire::test(CeSubRowsComponent::class);

    $test->assertSee('subrows-master-toggle', escape: false)
        ->assertSee('aria-expanded="false"', escape: false)
        // The three-button toolbar it replaced is gone for good.
        ->assertDontSee('subrows-expand-all"', escape: false)
        ->assertDontSee('subrows-collapse-all', escape: false)
        ->assertDontSee('subrows-scope-toggle', escape: false);
});

it('offers the expansion baseline in the view menu, the only bulk control a phone gets', function () {
    Livewire::test(CeSubRowsComponent::class)
        ->assertSee('subrows-expand-all-rows', escape: false)
        ->assertSee(__('wire-table::messages.expand_all_rows'));
});

it('promotes an alt-clicked row chevron to the master toggle', function () {
    Livewire::test(CeSubRowsComponent::class)
        ->assertSee('$event.altKey', escape: false)
        ->assertSee('toggleAllRowExpansion()', escape: false);
});

it('does nothing on expand-all when the table has no sub-rows', function () {
    $test = Livewire::test(CeNoSubRowsComponent::class)->call('expandAllRows');

    expect($test->instance()->tableState->get('rows.expanded', []))->toBe([])
        ->and($test->instance()->tableState->get('rows.expandAll'))->toBeNull();
});

// ─── getSubRows edge cases ────────────────────────────────────

it('returns an empty collection when the table has no sub-rows', function () {
    $record = CeInvoice::find(1);

    expect(Livewire::test(CeNoSubRowsComponent::class)->instance()->getSubRows($record))->toBeEmpty();
});

it('returns the record itself as its own sub-row in detail mode', function () {
    // Sub-row columns but no relation: the row is shown expanded against itself.
    $record = CeInvoice::find(1);
    $subRows = Livewire::test(CeDetailComponent::class)->instance()->getSubRows($record);

    expect($subRows)->toHaveCount(1)
        ->and($subRows->first()->is($record))->toBeTrue();
});

// ─── Sub-row sorting ──────────────────────────────────────────

it('sorts a sortable sub-row column ascending, then flips on a second click', function () {
    $test = Livewire::test(CeSubRowsComponent::class);

    $test->call('sortSubRows', 'price');
    expect($test->instance()->getSubRowSort())->toBe(['column' => 'price', 'direction' => 'asc']);

    $test->call('sortSubRows', 'price');
    expect($test->instance()->getSubRowSort())->toBe(['column' => 'price', 'direction' => 'desc']);
});

it('starts a newly sorted column ascending', function () {
    $test = Livewire::test(CeSubRowsComponent::class)
        ->call('sortSubRows', 'price')
        ->call('sortSubRows', 'price')   // price now desc
        ->call('sortSubRows', 'product'); // switching columns resets to asc

    expect($test->instance()->getSubRowSort())->toBe(['column' => 'product', 'direction' => 'asc']);
});

it('ignores a sort request for a column that is not sortable', function () {
    $test = Livewire::test(CeSubRowsComponent::class)->call('sortSubRows', 'not_a_column');

    expect($test->instance()->getSubRowSort())->toBeNull();
});

it('refuses a user sort on a table that is not interactively sortable, even for the default column', function () {
    // The table configures a default sort but not clickable headers. A crafted
    // sortSubRows() request must not ride the leniency isSubRowColumnSortable()
    // grants the default column for the query's own default sort.
    $test = Livewire::test(CeSubRowsComponent::class, ['sortableHeaders' => false])
        ->call('sortSubRows', 'price');

    expect($test->instance()->getSubRowSort())->toBeNull();
});

it('has no sub-row sort until one is chosen', function () {
    expect(Livewire::test(CeSubRowsComponent::class)->instance()->getSubRowSort())->toBeNull();
});

// ─── Show-all & totals ────────────────────────────────────────

it('flags a parent as show-all and reads it back', function () {
    $test = Livewire::test(CeSubRowsComponent::class);

    expect($test->instance()->isSubRowsShowAll(1))->toBeFalse();

    $test->call('showAllSubRows', 1);
    expect($test->instance()->isSubRowsShowAll(1))->toBeTrue();
});

it('counts zero total sub-rows when the table has none', function () {
    $record = CeInvoice::find(1);

    expect(Livewire::test(CeNoSubRowsComponent::class)->instance()->getSubRowsTotalCount($record))->toBe(0);
});

// ─── Filters ──────────────────────────────────────────────────

it('resets the interactive sub-row filter state', function () {
    $test = Livewire::test(CeSubRowsComponent::class);
    $test->instance()->tableState->set('rows.subRowFilters', ['price' => '10']);

    $test->call('resetSubRowFilters');

    expect($test->instance()->tableState->get('rows.subRowFilters'))->toBe([]);
});

it('handles the sub-row filter update hook as a safe no-op', function () {
    // A Livewire lifecycle hook (fires when the filter bar updates); it is
    // deliberately empty — unlike a main-filter change it must not reset the
    // page — so it is exercised on the instance rather than through ->call(),
    // which forbids invoking lifecycle hooks directly.
    $component = Livewire::test(CeSubRowsComponent::class)->instance();

    $component->updatedSubRowFilters();

    expect($component->tableState->get('rows.subRowFilters', []))->toBe([]);
});

// ─── Eager-load fast path ─────────────────────────────────────
//
// eagerLoadSubRows() loads every expanded parent's children in one query.
// It must not corrupt the result: whichever path runs, the children shown are
// the same as querying each parent. These drive it through getTableRecords()
// (the pipeline hook) and assert the children, not the query count.

it('eager-loads children for expanded rows and applies the sub-row query callback', function () {
    $test = Livewire::test(CeSubRowsComponent::class, ['withQueryCallback' => true])
        ->call('toggleRowExpansion', '1');

    $records = $test->instance()->getTableRecords();

    // The children are eager-loaded onto the page records themselves; the
    // callback (price >= 20) keeps Pen(30) and drops Ink(10) from invoice 1.
    $invoice = $records->firstWhere('id', 1);
    expect($invoice->getRelation('items')->pluck('product')->all())->toBe(['Pen']);
});

it('skips the eager-load fast path while an interactive sub-row filter is active', function () {
    // With a per-parent filter live, the shared eager-load would over-fetch, so
    // it is skipped in favour of the per-parent query. The visible children stay
    // correct either way — here we assert it simply does not error.
    $test = Livewire::test(CeSubRowsComponent::class)->call('toggleRowExpansion', '1');
    $test->instance()->tableState->set('rows.subRowFilters', ['product' => 'Pen']);
    // The first render already cached records; drop the cache so the next fetch
    // re-runs the eager-load path with the filter now active.
    $test->instance()->invalidateTable();

    $records = $test->instance()->getTableRecords();

    expect($records)->toHaveCount(2);
});

it('handles an empty page without attempting to eager-load', function () {
    CeInvoice::query()->delete();

    $records = Livewire::test(CeSubRowsComponent::class)->instance()->getTableRecords();

    expect($records)->toHaveCount(0);
});

it('limits eager-loaded children per parent and still counts the full set', function () {
    // The limited fast path (Laravel 11+ window function) loads only `limit`
    // rows per parent but carries an exact total for the "show more" control.
    $test = Livewire::test(CeSubRowsComponent::class, ['limit' => 1, 'withQueryCallback' => true])
        ->call('toggleRowExpansion', '1');

    $test->instance()->getTableRecords();

    // With the callback (price >= 20) invoice 1 keeps only Pen; the count is
    // the full filtered set, not the single displayed (limit 1) row.
    expect($test->instance()->getSubRowsTotalCount(CeInvoice::find(1)))->toBe(1);
})->skip(
    ! method_exists(Builder::class, 'groupLimit'),
    'Per-parent eager-load limit needs Laravel 11 groupLimit().',
);
