<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\DateFilter;
use NyonCode\WireTable\Filters\Filter;
use NyonCode\WireTable\Table;

// ─── Test Models ─────────────────────────────────────────────────────────────

class WtsfOrder extends Model
{
    protected $table = 'wtsf_orders';

    protected $guarded = [];

    public function entries(): HasMany
    {
        return $this->hasMany(WtsfEntry::class, 'order_id');
    }
}

class WtsfEntry extends Model
{
    protected $table = 'wtsf_entries';

    protected $guarded = [];
}

// ─── Test Component ──────────────────────────────────────────────────────────

class WtsfComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtsfOrder::class)
            ->paginated(false)
            ->columns([
                Column::make('number'),
                Column::make('entries_sum_amount')
                    ->sums('entries', 'amount')
                    ->summarizeSum(),
            ])
            ->filters([
                DateFilter::make('happened_at')->month()->subRows(),
            ])
            ->subRows('entries')
            ->subRowColumns([
                Column::make('label'),
                Column::make('amount'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wtsf_orders', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->timestamps();
    });

    Schema::create('wtsf_entries', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id');
        $table->string('label');
        $table->integer('amount');
        $table->date('happened_at');
        $table->timestamps();
    });

    $o1 = WtsfOrder::create(['number' => 'ORD-1']);
    WtsfEntry::create(['order_id' => $o1->id, 'label' => 'May entry', 'amount' => 10, 'happened_at' => '2026-05-15']);
    WtsfEntry::create(['order_id' => $o1->id, 'label' => 'June entry A', 'amount' => 20, 'happened_at' => '2026-06-01']);
    WtsfEntry::create(['order_id' => $o1->id, 'label' => 'June entry B', 'amount' => 30, 'happened_at' => '2026-06-20']);

    $o2 = WtsfOrder::create(['number' => 'ORD-2']);
    WtsfEntry::create(['order_id' => $o2->id, 'label' => 'May only', 'amount' => 40, 'happened_at' => '2026-05-02']);

    $o3 = WtsfOrder::create(['number' => 'ORD-3']);
    WtsfEntry::create(['order_id' => $o3->id, 'label' => 'June only', 'amount' => 50, 'happened_at' => '2026-06-09']);
});

afterEach(function () {
    Schema::dropIfExists('wtsf_entries');
    Schema::dropIfExists('wtsf_orders');
});

function wtsfComponent(?string $month = null): WtsfComponent
{
    $component = new WtsfComponent;
    $component->mountWithTable();

    if ($month !== null) {
        $component->tableState->set('filters', ['happened_at' => ['value' => $month]]);
    }

    return $component;
}

// ─── Parent filtering ────────────────────────────────────────────────────────

it('shows all parents when no sub-row filter is active', function () {
    $records = wtsfComponent()->getTableRecords();

    expect($records)->toHaveCount(3);
});

it('keeps only parents having a child matching the month filter', function () {
    $records = wtsfComponent('2026-06')->getTableRecords();

    expect($records->pluck('number')->all())->toBe(['ORD-1', 'ORD-3']);
});

it('keeps may-only parents for a may filter', function () {
    $records = wtsfComponent('2026-05')->getTableRecords();

    expect($records->pluck('number')->all())->toBe(['ORD-1', 'ORD-2']);
});

it('ignores a malformed month value', function () {
    $records = wtsfComponent('not-a-month')->getTableRecords();

    expect($records)->toHaveCount(3);
});

// ─── Displayed sub-rows ──────────────────────────────────────────────────────

it('limits displayed sub-rows to children matching the filter', function () {
    $component = wtsfComponent('2026-06');
    $order = $component->getTableRecords()->firstWhere('number', 'ORD-1');

    $subRows = $component->getSubRows($order);

    expect($subRows->pluck('label')->all())->toBe(['June entry A', 'June entry B']);
});

it('counts only matching sub-rows for the show-more affordance', function () {
    $component = wtsfComponent('2026-06');
    $order = $component->getTableRecords()->firstWhere('number', 'ORD-1');

    expect($component->getSubRowsTotalCount($order))->toBe(2);
});

// ─── Rollup aggregates & footer summaries ────────────────────────────────────

it('constrains rollup aggregates to matching sub-rows', function () {
    $component = wtsfComponent('2026-06');
    $records = $component->getTableRecords();

    expect($records->firstWhere('number', 'ORD-1')->entries_sum_amount)->toBe(50)
        ->and($records->firstWhere('number', 'ORD-3')->entries_sum_amount)->toBe(50);
});

it('leaves rollup aggregates untouched without an active filter', function () {
    $records = wtsfComponent()->getTableRecords();

    expect($records->firstWhere('number', 'ORD-1')->entries_sum_amount)->toBe(60);
});

it('sums only filtered sub-row records in the footer grand total', function () {
    $component = wtsfComponent('2026-06');
    $component->getTableRecords();

    $summaries = $component->computeTableSummaries('query');

    expect($summaries['entries_sum_amount'][0]['value'])->toBe(100);
});

it('sums all sub-row records in the footer without a filter', function () {
    $component = wtsfComponent();
    $component->getTableRecords();

    $summaries = $component->computeTableSummaries('query');

    expect($summaries['entries_sum_amount'][0]['value'])->toBe(150);
});

// ─── Generic (non-date) sub-row scoped filter ────────────────────────────────

it('supports a plain equality filter scoped to sub-rows', function () {
    $component = new WtsfGenericFilterComponent;
    $component->mountWithTable();
    $component->tableState->set('filters', ['label' => ['value' => 'June only']]);

    $records = $component->getTableRecords();

    expect($records->pluck('number')->all())->toBe(['ORD-3'])
        ->and($component->getSubRows($records->first())->pluck('label')->all())->toBe(['June only']);
});

class WtsfGenericFilterComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtsfOrder::class)
            ->paginated(false)
            ->columns([Column::make('number')])
            ->filters([
                Filter::make('label')->subRows(),
            ])
            ->subRows('entries')
            ->subRowColumns([
                Column::make('label'),
                Column::make('amount'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}
