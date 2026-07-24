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
 * The query cost of the card view and of the selection modes.
 *
 * Both are places where a per-record helper would be invisible in a unit test
 * and fatal on a real page: the card resolves slots per render and the selection
 * counts a filtered set. These pin the counts so a later change has to notice.
 */
class PerfInvoice extends Model
{
    protected $table = 'perf_invoices';

    protected $guarded = [];

    public $timestamps = false;

    /** @return HasMany<PerfItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PerfItem::class, 'invoice_id');
    }
}

class PerfItem extends Model
{
    protected $table = 'perf_items';

    protected $guarded = [];

    public $timestamps = false;
}

class MobPerfComponent extends Component
{
    use WithTable;

    public int $rowsPerPage = 25;

    public function table(Table $table): Table
    {
        return $table
            ->model(PerfInvoice::class)
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('customer'),
                BadgeColumn::make('status'),
                TextColumn::make('total')->alignRight(),
            ])
            ->stackedOnMobile()
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
    Schema::create('perf_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('customer');
        $table->string('status');
        $table->integer('total');
    });

    Schema::create('perf_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('product');
        $table->integer('line_total');
    });

    $invoices = [];
    foreach (range(1, 25) as $i) {
        $invoices[] = [
            'number' => "INV-{$i}", 'customer' => "Customer {$i}",
            'status' => 'open', 'total' => $i * 100,
        ];
    }
    PerfInvoice::insert($invoices);

    $items = [];
    foreach (range(1, 25) as $invoiceId) {
        foreach (range(1, 4) as $n) {
            $items[] = ['invoice_id' => $invoiceId, 'product' => "Item {$n}", 'line_total' => $n * 10];
        }
    }
    PerfItem::insert($items);
});

afterEach(function () {
    Schema::dropIfExists('perf_items');
    Schema::dropIfExists('perf_invoices');
});

/**
 * @return array{queries: int, html: string}
 */
function measureRender(array $params = []): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $html = Livewire::test(MobPerfComponent::class, $params)->html();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    return ['queries' => $queries, 'html' => $html];
}

it('resolves the card slots once per render, not once per record', function () {
    $component = new MobPerfComponent;
    $component->mountWithTable();
    $table = $component->getTable();
    $columns = $table->getColumns();

    $first = $table->getMobileCard($columns);

    // 25 cards asking for their slots must not rebuild the resolution 25 times.
    for ($i = 0; $i < 25; $i++) {
        expect($table->getMobileCard($columns))->toBe($first);
    }
});

it('renders 25 cards with collapsed children at a fixed query count', function () {
    $result = measureRender();

    // count + page + (no child queries at all while every row is collapsed)
    expect($result['queries'])->toBeLessThanOrEqual(3)
        ->and(substr_count($result['html'], 'data-testid="table-card"'))->toBe(25);
});

it('does not count children per card when the count would cost a query', function () {
    // Every card renders a collapsed toggle; none of them may issue a COUNT.
    $result = measureRender();

    expect($result['queries'])->toBeLessThanOrEqual(3)
        ->and($result['html'])->toContain(__('wire-table::messages.details'));
});

it('loads every card\'s children in one query when all rows are expanded', function () {
    DB::flushQueryLog();
    DB::enableQueryLog();

    Livewire::test(MobPerfComponent::class)->call('expandAllRows')->html();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // count + page + one eager load for all 25 parents' children — not 25.
    expect($queries)->toBeLessThanOrEqual(5);
});

it('adds no query for the select-all strip or the scope line', function () {
    // perPage below the row count so the scope line has something to escalate to.
    $result = measureRender(['rowsPerPage' => 10]);

    expect($result['html'])->toContain('table-card-select-all')
        ->and($result['html'])->toContain('table-selection-scope')
        // Same budget as a render without either control: both read state the
        // page already resolved.
        ->and($result['queries'])->toBeLessThanOrEqual(3);
});

it('costs one count for an all-matching selection, however often it is asked', function () {
    $test = Livewire::test(MobPerfComponent::class)->call('selectAllMatchingRecords');
    $component = $test->instance();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $component->getSelectedRecordsCount();
    $component->areAllVisibleSelected();
    $component->isRecordSelected('1');
    $component->getSelectedRecordsCount();

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBe(1);
});

it('walks a chunked selection in ceil(n / chunk) queries', function () {
    $test = Livewire::test(MobPerfComponent::class)->call('selectAllMatchingRecords');
    $component = $test->instance();
    $component->getSelectedRecordsCount(); // warm the memoized count

    DB::flushQueryLog();
    DB::enableQueryLog();

    $seen = 0;
    $component->eachSelectedRecord(function () use (&$seen) {
        $seen++;
    }, chunk: 10);

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // 25 records in chunks of 10 → 3 fetches, plus the terminating empty one.
    expect($seen)->toBe(25)
        ->and($queries)->toBeLessThanOrEqual(4);
});
