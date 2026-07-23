<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

use function Livewire\store;

// ─── Test Models ─────────────────────────────────────────────────────────────

class WtperfOrder extends Model
{
    protected $table = 'wtperf_orders';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(WtperfItem::class, 'order_id');
    }
}

class WtperfItem extends Model
{
    protected $table = 'wtperf_items';

    protected $guarded = [];
}

// ─── Test Components ─────────────────────────────────────────────────────────

class WtperfSummaryComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->columns([
                Column::make('number'),
                Column::make('total')->summarizeSum()->summarizeAvg()->summarizeRange(),
                Column::make('qty')->summarizeCount()->summarizeMax(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfMedianComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->columns([
                Column::make('total')->summarizeSum()->summarizeMedian(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfWhenComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->columns([
                Column::make('total')->summarize('sum', when: fn ($q) => $q->where('total', '>', 100)),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfGrandTotalsComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->columns([
                Column::make('number'),
            ])
            ->subRows('items')
            ->subRowColumns([
                Column::make('amount')->summarizeSum('Total')->summarizeMax('Max'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfSubRowLimitComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->defaultSort('number')
            ->columns([
                Column::make('number')->sortable(),
            ])
            ->subRows('items')
            ->subRowsLimit(1)
            ->subRowColumns([
                Column::make('amount'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/**
 * Forces the Laravel 10 path where the framework can't limit an eager load
 * per parent — exercises the in-memory fallback regardless of installed version.
 */
class WtperfSubRowLimitLegacyComponent extends WtperfSubRowLimitComponent
{
    protected function supportsPerParentEagerLimit(): bool
    {
        return false;
    }
}

class WtperfCachedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->defaultSort('number')
            ->perPage(2)
            ->cacheQuery(ttl: 600)
            ->columns([
                Column::make('number')->sortable(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfCustomKeyComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->defaultSort('number')
            ->perPage(2)
            ->cacheQuery(ttl: 600, key: 'wtperf-fixed-key')
            ->columns([
                Column::make('number')->sortable(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfPollComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->poll('5s')
            ->pollChangeDetection()
            ->columns([
                Column::make('number'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfPollClosureComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->poll('5s')
            ->pollChangeDetection(fn ($query) => (string) $query->max('qty'))
            ->columns([
                Column::make('number'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtperfSelectableComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtperfOrder::class)
            ->paginated(false)
            ->selectable()
            ->columns([
                Column::make('number'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wtperf_orders', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->integer('total');
        $table->integer('qty');
        $table->timestamps();
    });

    Schema::create('wtperf_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id');
        $table->integer('amount');
        $table->timestamps();
    });

    $a = WtperfOrder::create(['number' => 'A', 'total' => 100, 'qty' => 1]);
    $b = WtperfOrder::create(['number' => 'B', 'total' => 200, 'qty' => 2]);
    WtperfOrder::create(['number' => 'C', 'total' => 300, 'qty' => 3]);

    WtperfItem::create(['order_id' => $a->id, 'amount' => 10]);
    WtperfItem::create(['order_id' => $a->id, 'amount' => 20]);
    WtperfItem::create(['order_id' => $b->id, 'amount' => 30]);
});

afterEach(function () {
    Schema::dropIfExists('wtperf_orders');
    Schema::dropIfExists('wtperf_items');
});

/**
 * Count the SQL queries executed inside $fn.
 */
function wtperfQueryCount(callable $fn): int
{
    DB::enableQueryLog();
    DB::flushQueryLog();
    $fn();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

// ─── Summary batching ────────────────────────────────────────────────────────

it('batches all SQL-native query-scope summaries into a single query', function () {
    $component = new WtperfSummaryComponent;
    $component->mountWithTable();
    $component->getTableRecords(); // warm the cached query plan

    $summaries = [];
    $queries = wtperfQueryCount(function () use ($component, &$summaries) {
        $summaries = $component->computeTableSummaries('query');
    });

    // 5 summaries across 2 columns previously ran 5+ aggregate queries.
    expect($queries)->toBe(1)
        ->and($summaries['total'][0]['value'])->toEqual(600)   // sum
        ->and($summaries['total'][1]['value'])->toEqual(200)   // avg
        ->and($summaries['total'][2]['value'])->toBe('100 – 300') // range
        ->and($summaries['qty'][0]['value'])->toBe(3)          // count
        ->and($summaries['qty'][1]['value'])->toEqual(3);      // max
});

it('returns batched results identical to empty-set semantics', function () {
    WtperfItem::query()->delete();
    WtperfOrder::query()->delete();

    $component = new WtperfSummaryComponent;
    $component->mountWithTable();
    $component->getTableRecords();

    $summaries = $component->computeTableSummaries('query');

    expect($summaries['total'][0]['value'])->toEqual(0) // Builder::sum() coalesces to 0
        ->and($summaries['qty'][0]['value'])->toBe(0);
});

it('falls back to per-summary queries for non-SQL-native types', function () {
    $component = new WtperfMedianComponent;
    $component->mountWithTable();
    $component->getTableRecords();

    $summaries = [];
    $queries = wtperfQueryCount(function () use ($component, &$summaries) {
        $summaries = $component->computeTableSummaries('query');
    });

    // 1 batched query (sum) + 1 pluck fallback (median)
    expect($queries)->toBe(2)
        ->and($summaries['total'][0]['value'])->toEqual(600)
        ->and($summaries['total'][1]['value'])->toBe(200.0);
});

it('falls back to per-summary queries for when() restrictions', function () {
    $component = new WtperfWhenComponent;
    $component->mountWithTable();
    $component->getTableRecords();

    $summaries = $component->computeTableSummaries('query');

    // Only B (200) + C (300) pass the when() restriction.
    expect($summaries['total'][0]['value'])->toEqual(500);
});

it('batches sub-row grand totals into a single query', function () {
    $component = new WtperfGrandTotalsComponent;
    $component->mountWithTable();
    $component->getTableRecords();
    $component->computeSubRowGrandTotals(); // warm the parent query plan

    $totals = [];
    $queries = wtperfQueryCount(function () use ($component, &$totals) {
        $totals = $component->computeSubRowGrandTotals();
    });

    expect($queries)->toBe(1)
        ->and($totals['amount'][0]['value'])->toEqual(60)  // sum across all parents
        ->and($totals['amount'][1]['value'])->toEqual(30); // max
});

// ─── Limited sub-row eager loading ───────────────────────────────────────────

it('eager-loads only the limited sub-rows plus an exact count', function () {
    $component = new WtperfSubRowLimitComponent;
    $component->mountWithTable();
    $component->tableState->set('rows.expandAll', true); // every parent renders sub-rows

    $records = $component->getTableRecords();
    $a = $records->firstWhere('number', 'A'); // has 2 items, limit is 1

    expect($a->relationLoaded('items'))->toBeTrue()
        ->and($a->getRelation('items'))->toHaveCount(1)  // memory holds only the limit
        ->and((int) $a->items_count)->toBe(2);           // exact total shipped alongside

    // The show-more total reads the loaded count — no extra query.
    $queries = wtperfQueryCount(fn () => $component->getSubRowsTotalCount($a));

    expect($queries)->toBe(0)
        ->and($component->getSubRowsTotalCount($a))->toBe(2)
        ->and($component->getSubRows($a))->toHaveCount(1);
})->skip(
    ! method_exists(Builder::class, 'groupLimit'),
    'Native per-parent eager-load limits require Laravel 11+ (Query\Builder::groupLimit).',
);

it('falls back to in-memory limiting when the framework lacks per-parent limits (Laravel 10)', function () {
    $component = new WtperfSubRowLimitLegacyComponent;
    $component->mountWithTable();
    $component->tableState->set('rows.expandAll', true);

    $records = $component->getTableRecords();
    $a = $records->firstWhere('number', 'A'); // has 2 items, limit is 1

    // Without native per-parent limits the full set is eager-loaded once...
    expect($a->relationLoaded('items'))->toBeTrue()
        ->and($a->getRelation('items'))->toHaveCount(2) // full set in memory
        ->and($a->items_count)->toBeNull();             // no loadCount shipped

    // ...and the display limit + total stay correct (count from the loaded set,
    // no extra query).
    $queries = wtperfQueryCount(fn () => $component->getSubRowsTotalCount($a));

    expect($queries)->toBe(0)
        ->and($component->getSubRowsTotalCount($a))->toBe(2)
        ->and($component->getSubRows($a))->toHaveCount(1);
});

it('eager-loads the full set for show-all parents', function () {
    $component = new WtperfSubRowLimitComponent;
    $component->mountWithTable();
    $component->tableState->set('rows.expandAll', true);

    $aKey = WtperfOrder::where('number', 'A')->value('id');
    $component->showAllSubRows((string) $aKey);

    $records = $component->getTableRecords();
    $a = $records->firstWhere('number', 'A');

    expect($a->getRelation('items'))->toHaveCount(2)
        ->and($component->getSubRows($a))->toHaveCount(2);
});

it('falls back to the query when show-all flips after a limited load', function () {
    $component = new WtperfSubRowLimitComponent;
    $component->mountWithTable();
    $component->tableState->set('rows.expandAll', true);

    $records = $component->getTableRecords(); // limited load happens here
    $a = $records->firstWhere('number', 'A');

    $component->showAllSubRows((string) $a->getKey());

    expect($component->getSubRows($a))->toHaveCount(2);
});

// ─── Query cache: per-page keys ──────────────────────────────────────────────

it('caches each page under its own key', function () {
    config()->set('cache.default', 'array');

    $component = new WtperfCachedComponent;
    $component->mountWithTable();

    // Mirror what Livewire does during a request: resolve the current page from
    // component state. (Reading $paginators directly — getPage() itself falls
    // back to this resolver, which would recurse.)
    Paginator::currentPageResolver(fn () => $component->paginators['page'] ?? 1);

    expect($component->getTableRecords()->pluck('number')->all())->toBe(['A', 'B']);

    $component->setPage(2);
    $component->invalidateTable();

    // Without the page suffix in the cache key this returns page 1 again.
    expect($component->getTableRecords()->pluck('number')->all())->toBe(['C']);
});

it('caches each page separately even with a custom cache key', function () {
    config()->set('cache.default', 'array');

    $component = new WtperfCustomKeyComponent;
    $component->mountWithTable();

    // Mirror what Livewire does during a request: resolve the current page from
    // component state. (Reading $paginators directly — getPage() itself falls
    // back to this resolver, which would recurse.)
    Paginator::currentPageResolver(fn () => $component->paginators['page'] ?? 1);

    expect($component->getTableRecords()->pluck('number')->all())->toBe(['A', 'B']);

    $component->setPage(2);
    $component->invalidateTable();

    expect($component->getTableRecords()->pluck('number')->all())->toBe(['C']);
});

// ─── Poll change detection ───────────────────────────────────────────────────

it('skips the poll re-render when data is unchanged', function () {
    $component = new WtperfPollComponent;
    $component->mountWithTable();

    $component->refreshTable(); // first poll stores the checksum and renders
    expect(store($component)->get('skipRender'))->toBeNull();

    $component->refreshTable(); // nothing changed — render skipped
    expect(store($component)->get('skipRender'))->toBeTrue();
});

it('re-renders the poll when data changed', function () {
    $component = new WtperfPollComponent;
    $component->mountWithTable();

    $component->refreshTable(); // store checksum

    WtperfOrder::create(['number' => 'D', 'total' => 50, 'qty' => 9]);
    $component->invalidateTable();

    $component->refreshTable();
    expect(store($component)->get('skipRender'))->toBeNull();
});

it('supports a custom poll checksum closure', function () {
    $component = new WtperfPollClosureComponent;
    $component->mountWithTable();

    $component->refreshTable();
    $component->refreshTable();
    expect(store($component)->get('skipRender'))->toBeTrue()
        ->and($component->tableState->get('polling.checksum'))->toBe('3'); // max(qty)
});

// ─── Selected records memoization ────────────────────────────────────────────

it('memoizes selected records within a request', function () {
    $component = new WtperfSelectableComponent;
    $component->mountWithTable();
    $component->toggleRecordSelection('1');

    $queries = wtperfQueryCount(function () use ($component) {
        $component->getSelectedRecords();
        $component->getSelectedRecords();
        $component->getSelectedRecords();
    });

    expect($queries)->toBe(1);
});

it('refreshes the selected records memo when the selection mutates', function () {
    $component = new WtperfSelectableComponent;
    $component->mountWithTable();

    $component->toggleRecordSelection('1');
    expect($component->getSelectedRecords()->pluck('number')->all())->toBe(['A']);

    $component->toggleRecordSelection('2');
    expect($component->getSelectedRecords()->pluck('number')->sort()->values()->all())->toBe(['A', 'B']);

    $component->deselectAllRecords();
    expect($component->getSelectedRecords())->toBeEmpty();
});

// ─── Alpine-driven selection ─────────────────────────────────────────────────

it('renders Alpine-driven selection without per-click server roundtrips', function () {
    $html = Livewire::test(WtperfSelectableComponent::class)->html();

    expect($html)
        ->toContain('data-page-keys=')                          // page keys for select-all
        ->toContain("entangle('tableState.selection.records')") // deferred client state
        ->toContain('x-show="selectedCount > 0"')               // Alpine selection bar
        ->not->toContain('wire:click="toggleRecordSelection');  // no roundtrip per click
});

it('keeps the server selection state authoritative for bulk flows', function () {
    // Entangled updates land in tableState before any action call — server
    // methods keep reading the same path.
    $component = Livewire::test(WtperfSelectableComponent::class)
        ->set('tableState.selection.records', ['1', '2'])
        ->instance();

    expect($component->getSelectedRecordKeys())->toBe(['1', '2'])
        ->and($component->getSelectedRecords()->pluck('number')->sort()->values()->all())->toBe(['A', 'B']);
});

// ─── Legacy magic properties keep working ────────────────────────────────────

it('still resolves legacy magic properties through the state container', function () {
    $component = new WtperfSelectableComponent;
    $component->mountWithTable();

    $component->tableState->set('search', 'abc');
    expect($component->tableSearch)->toBe('abc');

    $component->tableSearch = 'def';
    expect($component->tableState->get('search'))->toBe('def');

    $component->tableState->set('rows.expandAll', true);
    expect($component->flattenMode)->toBeTrue();
});
