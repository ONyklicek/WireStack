<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

// ─── Test Models ─────────────────────────────────────────────────────────────

class WtInvoice extends Model
{
    protected $table = 'wt_invoices';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(WtItem::class, 'invoice_id');
    }
}

class WtItem extends Model
{
    protected $table = 'wt_items';

    protected $guarded = [];
}

// ─── Test Component ──────────────────────────────────────────────────────────

class WtSubRowsComponent extends Component
{
    use WithTable;

    public int $subRowsLimit = 0;

    public bool $flatten = false;

    public function table(Table $table): Table
    {
        $table = $table
            ->model(WtInvoice::class)
            ->paginated(false)
            ->columns([
                Column::make('number'),
                Column::make('items_total')
                    ->sums('items', 'price')
                    ->summaryDecimals(2)
                    ->summarizeSum(),
            ])
            ->subRows('items')
            ->subRowColumns([
                Column::make('product'),
                Column::make('price'),
            ])
            ->subRowsSortable(default: 'price', direction: 'asc');

        if ($this->subRowsLimit > 0) {
            $table->subRowsLimit($this->subRowsLimit);
        }

        if ($this->flatten) {
            $table->flattenSubRows();
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wt_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->timestamps();
    });

    Schema::create('wt_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('product');
        $table->integer('price');
        $table->timestamps();
    });

    foreach (['INV-1', 'INV-2', 'INV-3'] as $i => $number) {
        $invoice = WtInvoice::create(['number' => $number]);
        // 3 items each, deliberately out of price order
        WtItem::create(['invoice_id' => $invoice->id, 'product' => 'C', 'price' => 30]);
        WtItem::create(['invoice_id' => $invoice->id, 'product' => 'A', 'price' => 10]);
        WtItem::create(['invoice_id' => $invoice->id, 'product' => 'B', 'price' => 20]);
    }
});

afterEach(function () {
    Schema::dropIfExists('wt_items');
    Schema::dropIfExists('wt_invoices');
});

// ─── A5: summary scope ───────────────────────────────────────────────────────

it('defaults the summary scope to query', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = false; // bootstrap tableState

    expect($component->getSummaryScope())->toBe('query');
});

it('can switch the summary scope to page', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = false;

    $component->setSummaryScope('page');

    expect($component->getSummaryScope())->toBe('page');
});

it('ignores an unknown summary scope', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = false;

    $component->setSummaryScope('bogus');

    expect($component->getSummaryScope())->toBe('query');
});

it('falls back to query when selection scope is active but nothing is selected', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = false;

    $component->setSummaryScope('selection');

    expect($component->getSummaryScope())->toBe('query');
});

it('omits selection from scope options when nothing is selected', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = false;

    expect($component->getSummaryScopeOptions())->toBe(['query', 'page']);
});

it('includes selection in scope options when rows are selected', function () {
    $component = new WtSubRowsComponent;
    $component->selectedRecords = [1, 2];

    expect($component->getSummaryScopeOptions())->toContain('selection')
        ->and($component->getSummaryScope())->toBe('query');
});

// ─── B3: eager loading ───────────────────────────────────────────────────────

it('eager-loads sub-rows for all rows in flatten mode in a single query', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = true;

    DB::flushQueryLog();
    DB::enableQueryLog();

    $records = $component->getTableRecords();
    $collection = $records instanceof EloquentCollection ? $records : collect($records->all());

    $queriesAfterFetch = count(DB::getQueryLog());

    // Every record should already have its relation loaded.
    expect($collection)->toHaveCount(3)
        ->and($collection->every(fn ($r) => $r->relationLoaded('items')))->toBeTrue();

    // Reading sub-rows must not issue any further queries.
    foreach ($collection as $record) {
        $component->getSubRows($record);
    }

    expect(count(DB::getQueryLog()))->toBe($queriesAfterFetch);

    DB::disableQueryLog();
});

it('does not eager-load sub-rows when no rows are expanded', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = false;

    $records = $component->getTableRecords();
    $collection = $records instanceof EloquentCollection ? $records : collect($records->all());

    expect($collection->every(fn ($r) => ! $r->relationLoaded('items')))->toBeTrue();
});

it('returns sorted sub-rows from the eager-loaded relation', function () {
    $component = new WtSubRowsComponent;
    $component->flattenMode = true;

    $records = $component->getTableRecords();
    $first = (($records instanceof EloquentCollection) ? $records : collect($records->all()))->first();

    $prices = $component->getSubRows($first)->pluck('price')->all();

    expect($prices)->toBe([10, 20, 30]);
});

it('applies the per-parent limit in memory when eager-loaded', function () {
    $component = new WtSubRowsComponent;
    $component->subRowsLimit = 2;
    $component->flattenMode = true;

    $records = $component->getTableRecords();
    $first = (($records instanceof EloquentCollection) ? $records : collect($records->all()))->first();

    // Limited view shows 2, but the total count still reflects all 3.
    expect($component->getSubRows($first))->toHaveCount(2)
        ->and($component->getSubRowsTotalCount($first))->toBe(3);
});

it('reveals all sub-rows after showAllSubRows even with a limit', function () {
    $component = new WtSubRowsComponent;
    $component->subRowsLimit = 2;
    $component->flattenMode = true;

    $records = $component->getTableRecords();
    $first = (($records instanceof EloquentCollection) ? $records : collect($records->all()))->first();

    $component->showAllSubRows($first->getKey());

    expect($component->getSubRows($first))->toHaveCount(3);
});

// ─── flattenSubRows() config feeds the expansion baseline ────────────────────

it('opens every row when the deprecated flattenSubRows() config is set', function () {
    $component = new WtSubRowsComponent;
    $component->flatten = true;
    $component->mountWithTable();

    // No runtime flag is seeded: the config *is* the baseline until the user
    // chooses otherwise.
    expect($component->flattenMode)->toBeNull()
        ->and($component->expandsSubRowsByDefault())->toBeTrue()
        ->and($component->isRowExpanded(1))->toBeTrue();
});

it('keeps rows closed without the config', function () {
    $component = new WtSubRowsComponent;
    $component->mountWithTable();

    expect($component->expandsSubRowsByDefault())->toBeFalse()
        ->and($component->isRowExpanded(1))->toBeFalse();
});

// ─── Footer render (A5 + C2 end-to-end) ──────────────────────────────────────

it('renders the summary footer with a grand total and a scope toggle', function () {
    Livewire::test(WtSubRowsComponent::class)
        ->assertSee(__('wire-table::messages.summary_scope_label'))
        ->assertSee(__('wire-table::messages.summary_scope_page'))
        // grand total of all item prices: 3 invoices × (10+20+30) = 180,
        // formatted via summaryDecimals(2) with a comma decimal separator.
        ->assertSee('180,00');
});
