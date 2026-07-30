<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Table;

/*
 * Table::subRowsHideWhenEmpty() — the expander disappears from rows with no
 * children. The whole point is that it costs no query per row, so most of these
 * assert queries, not just visibility: the table's own query carries a
 * constrained presence count and the check reads it off the record.
 */

class HweOrder extends Model
{
    protected $table = 'hwe_orders';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<HweItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(HweItem::class, 'order_id');
    }
}

class HweItem extends Model
{
    protected $table = 'hwe_items';

    protected $guarded = [];

    public $timestamps = false;
}

class HweComponent extends Component
{
    use WithTable;

    public bool $hideWhenEmpty = true;

    /** Constrain the displayed children (and so the presence count) to price >= 20. */
    public bool $withQueryCallback = false;

    /** Add a rollup count column over the same relation. */
    public bool $withRollup = false;

    /** Reject every record before the count is ever reached. */
    public bool $rejectAll = false;

    /** Offer a sub-row scoped main filter (Filter::subRows()). */
    public bool $withScopedFilter = false;

    public function table(Table $table): Table
    {
        $columns = [Column::make('number')];

        if ($this->withRollup) {
            $columns[] = Column::make('items_count')->counts('items');
        }

        $table = $table
            ->model(HweOrder::class)
            ->paginated(false)
            ->columns($columns)
            ->subRows('items')
            ->subRowColumns([Column::make('product'), Column::make('price')])
            ->subRowsHideWhenEmpty($this->hideWhenEmpty);

        if ($this->withQueryCallback) {
            $table->subRowQuery(fn ($query) => $query->where('price', '>=', 20));
        }

        if ($this->rejectAll) {
            $table->subRowsVisible(false);
        }

        if ($this->withScopedFilter) {
            $table->filters([Filter::make('price')->subRows()]);
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function hweQueryCount(callable $fn): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $fn();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

beforeEach(function () {
    Schema::create('hwe_orders', function (Blueprint $t) {
        $t->id();
        $t->string('number');
    });
    Schema::create('hwe_items', function (Blueprint $t) {
        $t->id();
        $t->foreignId('order_id');
        $t->string('product');
        $t->integer('price');
    });

    // A: two children. B: none. C: one cheap child (below the callback's floor).
    HweOrder::insert([
        ['id' => 1, 'number' => 'A'],
        ['id' => 2, 'number' => 'B'],
        ['id' => 3, 'number' => 'C'],
    ]);
    HweItem::insert([
        ['order_id' => 1, 'product' => 'Pen', 'price' => 30],
        ['order_id' => 1, 'product' => 'Ink', 'price' => 10],
        ['order_id' => 3, 'product' => 'Clip', 'price' => 5],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('hwe_items');
    Schema::dropIfExists('hwe_orders');
});

// ─── The switch ───────────────────────────────────────────────

it('keeps the expander on childless rows until asked not to', function () {
    $table = Table::make()->model(HweOrder::class)->subRows('items');

    expect($table->hidesSubRowsWhenEmpty())->toBeFalse()
        ->and($table->hasSubRowsFor(HweOrder::find(2)))->toBeTrue();
});

it('drops the expander from a record with no children', function () {
    $table = Table::make()->model(HweOrder::class)->subRows('items')->subRowsHideWhenEmpty();

    expect($table->hasSubRowsFor(HweOrder::find(1)))->toBeTrue()
        ->and($table->hasSubRowsFor(HweOrder::find(2)))->toBeFalse();
});

it('can be switched back off', function () {
    $table = Table::make()->model(HweOrder::class)->subRows('items')->subRowsHideWhenEmpty();
    expect($table->hasSubRowsFor(HweOrder::find(2)))->toBeFalse();

    $table->subRowsHideWhenEmpty(false);
    expect($table->hidesSubRowsWhenEmpty())->toBeFalse()
        ->and($table->hasSubRowsFor(HweOrder::find(2)))->toBeTrue();
});

it('ignores the flag in detail-row mode, which has no relation to count', function () {
    $table = Table::make()
        ->model(HweOrder::class)
        ->subRowColumns([Column::make('number')])
        ->subRowsHideWhenEmpty();

    expect($table->hasSubRowsFor(HweOrder::find(2)))->toBeTrue();
});

// ─── The cost ─────────────────────────────────────────────────

it('answers for every row on the page without a single extra query', function () {
    // The naive implementation is one COUNT per row. The planner's presence
    // count makes it an attribute read — this is the test that says so.
    $component = Livewire::test(HweComponent::class)->instance();
    $records = $component->getTableRecords();
    $table = $component->getTable();

    $queries = hweQueryCount(function () use ($records, $table) {
        foreach ($records as $record) {
            $table->hasSubRowsFor($record);
        }
    });

    expect($queries)->toBe(0)
        ->and($table->hasSubRowsFor($records->firstWhere('id', 1)))->toBeTrue()
        ->and($table->hasSubRowsFor($records->firstWhere('id', 2)))->toBeFalse();
});

it('carries the presence count under its own alias, leaving a rollup count intact', function () {
    // Both want a count of the same relation. Sharing the default alias would
    // put two identically-named selects in one query.
    $records = Livewire::test(HweComponent::class, ['withRollup' => true])->instance()->getTableRecords();

    $a = $records->firstWhere('id', 1);

    expect($a->getAttribute('items_count'))->toBe(2)
        ->and($a->getAttribute(Table::SUB_ROWS_PRESENCE_COUNT))->toBe(2)
        ->and($records->firstWhere('id', 2)->getAttribute(Table::SUB_ROWS_PRESENCE_COUNT))->toBe(0);
});

it('falls back to a single EXISTS for a record the table never fetched', function () {
    $table = Table::make()->model(HweOrder::class)->subRows('items')->subRowsHideWhenEmpty();
    $record = HweOrder::find(1);

    $queries = hweQueryCount(fn () => $table->hasSubRowsFor($record));

    // One query for the first ask, none for the second — the answer is memoized.
    expect($queries)->toBe(1)
        ->and(hweQueryCount(fn () => $table->hasSubRowsFor($record)))->toBe(0);
});

it("reads a caller's own withCount instead of querying", function () {
    // A base query that already counts the relation (the mobile card's
    // collapsed count reads the same attribute) answers this for free too.
    $table = Table::make()->model(HweOrder::class)->subRows('items')->subRowsHideWhenEmpty();
    $records = HweOrder::withCount('items')->get();

    $queries = hweQueryCount(function () use ($records, $table) {
        foreach ($records as $record) {
            $table->hasSubRowsFor($record);
        }
    });

    expect($queries)->toBe(0)
        ->and($table->hasSubRowsFor($records->firstWhere('id', 1)))->toBeTrue()
        ->and($table->hasSubRowsFor($records->firstWhere('id', 2)))->toBeFalse();
});

it('reads an already-loaded relation instead of querying it', function () {
    $table = Table::make()->model(HweOrder::class)->subRows('items')->subRowsHideWhenEmpty();
    $records = HweOrder::with('items')->get();

    $queries = hweQueryCount(function () use ($records, $table) {
        foreach ($records as $record) {
            $table->hasSubRowsFor($record);
        }
    });

    expect($queries)->toBe(0)
        ->and($table->hasSubRowsFor($records->firstWhere('id', 1)))->toBeTrue()
        ->and($table->hasSubRowsFor($records->firstWhere('id', 2)))->toBeFalse();
});

it('never counts a record the visibility condition already rejected', function () {
    // subRowsVisible() runs first, so the cheap "no" wins before the EXISTS.
    $table = Table::make()
        ->model(HweOrder::class)
        ->subRows('items')
        ->subRowsHideWhenEmpty()
        ->subRowsVisible(false);

    $record = HweOrder::find(1);
    $queries = hweQueryCount(fn () => $table->hasSubRowsFor($record));

    expect($queries)->toBe(0)
        ->and($table->hasSubRowsFor($record))->toBeFalse();
});

// ─── Which children count ─────────────────────────────────────

it('counts only the children subRowQuery would display', function () {
    // C's single child (5) is below the callback's floor, so its panel would
    // open onto the empty-state message — hide the expander instead.
    $component = Livewire::test(HweComponent::class, ['withQueryCallback' => true])->instance();
    $records = $component->getTableRecords();
    $table = $component->getTable();

    expect($table->hasSubRowsFor($records->firstWhere('id', 1)))->toBeTrue()
        ->and($table->hasSubRowsFor($records->firstWhere('id', 3)))->toBeFalse();
});

it('counts only the children a sub-row scoped filter leaves', function () {
    $component = Livewire::test(HweComponent::class, ['withScopedFilter' => true])
        ->set('tableState.filters.price', 30)
        ->instance();

    $records = $component->getTableRecords();
    $table = $component->getTable();

    // A keeps its Pen(30); C's Clip(5) is filtered out, and the scoped filter
    // has already dropped B and C from the page entirely.
    expect($table->hasSubRowsFor($records->firstWhere('id', 1)))->toBeTrue()
        ->and($records->pluck('id')->all())->toBe([1]);
});

it('leaves the expander alone for the interactive per-parent filter bar', function () {
    // Those values change per parent as the user types; folding them in would
    // make the expander appear and disappear under the cursor. A parent whose
    // children are all filtered out that way still opens, to the empty message.
    $test = Livewire::test(HweComponent::class);
    $test->instance()->tableState->set('rows.subRowFilters', ['product' => 'nothing-matches']);
    $test->instance()->invalidateTable();

    $records = $test->instance()->getTableRecords();

    expect($test->instance()->getTable()->hasSubRowsFor($records->firstWhere('id', 1)))->toBeTrue();
});

// ─── Rendering ────────────────────────────────────────────────

it('renders a chevron only for the rows that have children', function () {
    // A (two children) and C (one) keep theirs; childless B loses it.
    $html = Livewire::test(HweComponent::class)->html();
    expect(substr_count($html, 'data-testid="table-row-expand"'))->toBe(2);

    // With the callback, C's only child falls below the floor too.
    $constrained = Livewire::test(HweComponent::class, ['withQueryCallback' => true])->html();
    expect(substr_count($constrained, 'data-testid="table-row-expand"'))->toBe(1);

    // Off again: every row gets one back, childless or not.
    $unrestricted = Livewire::test(HweComponent::class, ['hideWhenEmpty' => false])->html();
    expect(substr_count($unrestricted, 'data-testid="table-row-expand"'))->toBe(3);
});

it('does not eager-load or render a panel for a childless row', function () {
    $records = Livewire::test(HweComponent::class, ['hideWhenEmpty' => true])
        ->call('expandAllRows')
        ->instance()
        ->getTableRecords();

    expect($records->firstWhere('id', 1)->relationLoaded('items'))->toBeTrue()
        ->and($records->firstWhere('id', 2)->relationLoaded('items'))->toBeFalse();
});
