<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\BadgeColumn;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/**
 * Wall-clock and query numbers for the card view at realistic sizes.
 *
 * Timings are reported, not asserted: a threshold in wall-clock time is a
 * flaky test on shared CI. What *is* asserted is the shape — the query count
 * must not grow with the number of rows, and the card view must not cost
 * meaningfully more than the table it replaces. Run with
 * `--group=benchmark` to see the numbers.
 *
 * @group benchmark
 */
class BenchInvoice extends Model
{
    protected $table = 'bench_invoices';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<BenchItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BenchItem::class, 'invoice_id');
    }
}

class BenchItem extends Model
{
    protected $table = 'bench_items';

    protected $guarded = [];

    public $timestamps = false;
}

class BenchComponent extends Component
{
    use WithTable;

    public bool $stacked = true;

    public int $rowsPerPage = 50;

    public function table(Table $table): Table
    {
        return $table
            ->model(BenchInvoice::class)
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('customer'),
                BadgeColumn::make('status'),
                TextColumn::make('reference'),
                TextColumn::make('total')->alignRight(),
            ])
            ->stackedOnMobile($this->stacked)
            ->selectable()
            ->paginated()
            ->perPage($this->rowsPerPage)
            ->subRows('items')
            ->subRowColumns([
                TextColumn::make('product'),
                TextColumn::make('line_total')->alignRight()->summarizeSum('Subtotal', scope: 'subRows'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('bench_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('customer');
        $table->string('status');
        $table->string('reference');
        $table->integer('total');
    });

    Schema::create('bench_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('product');
        $table->integer('line_total');
    });

    $invoices = [];
    foreach (range(1, 500) as $i) {
        $invoices[] = [
            'number' => "INV-{$i}", 'customer' => "Customer {$i}", 'status' => 'open',
            'reference' => "2026/{$i}", 'total' => $i * 13,
        ];
    }
    foreach (array_chunk($invoices, 100) as $chunk) {
        BenchInvoice::insert($chunk);
    }

    $items = [];
    foreach (range(1, 500) as $invoiceId) {
        foreach (range(1, 4) as $n) {
            $items[] = ['invoice_id' => $invoiceId, 'product' => "Item {$n}", 'line_total' => $n * 10];
        }
    }
    foreach (array_chunk($items, 200) as $chunk) {
        BenchItem::insert($chunk);
    }
});

afterEach(function () {
    Schema::dropIfExists('bench_items');
    Schema::dropIfExists('bench_invoices');
});

/**
 * @return array{ms: float, queries: int, bytes: int}
 */
function bench(array $params, ?callable $before = null): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $start = hrtime(true);
    $test = Livewire::test(BenchComponent::class, $params);
    if ($before !== null) {
        $before($test);
    }
    $html = $test->html();
    $ms = (hrtime(true) - $start) / 1_000_000;

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['ms' => round($ms, 1), 'queries' => $queries, 'bytes' => strlen($html)];
}

it('keeps the query count flat as the page grows', function () {
    $small = bench(['rowsPerPage' => 10]);
    $large = bench(['rowsPerPage' => 100]);

    dump([
        'card view · 10 rows' => $small,
        'card view · 100 rows' => $large,
    ]);

    // Ten times the rows must not mean more queries — that is the N+1 guard the
    // wall-clock number cannot give.
    expect($large['queries'])->toBe($small['queries']);
})->group('benchmark');

it('costs no more than the table view it replaces', function () {
    $table = bench(['stacked' => false, 'rowsPerPage' => 50]);
    $cards = bench(['stacked' => true, 'rowsPerPage' => 50]);

    dump([
        'table view · 50 rows' => $table,
        'card view · 50 rows' => $cards,
    ]);

    expect($cards['queries'])->toBe($table['queries']);
})->group('benchmark');

it('keeps expansion of a whole page to one child query', function () {
    // Both sides run two renders: ->call() re-renders, so a single-render
    // baseline would score the extra render as if it were child queries.
    $collapsed = bench(['rowsPerPage' => 50], fn ($test) => $test->call('collapseAllRows'));
    $expanded = bench(['rowsPerPage' => 50], fn ($test) => $test->call('expandAllRows'));

    dump([
        'collapsed · 50 rows' => $collapsed,
        'expanded · 50 rows (200 children)' => $expanded,
    ]);

    // One eager load, not one per parent.
    expect($expanded['queries'] - $collapsed['queries'])->toBeLessThanOrEqual(2);
})->group('benchmark');

it('answers an all-matching selection without touching the rows', function () {
    $keyed = bench(['rowsPerPage' => 50], fn ($test) => $test->call('selectAllRecords'));
    $all = bench(['rowsPerPage' => 50], fn ($test) => $test->call('selectAllMatchingRecords'));

    dump([
        'select page (50 keys)' => $keyed,
        'select all 500 matching' => $all,
    ]);

    // Selecting 500 rows costs one COUNT more than selecting 50 — not 10× the work.
    expect($all['queries'] - $keyed['queries'])->toBeLessThanOrEqual(2);
})->group('benchmark');
