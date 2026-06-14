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
use NyonCode\WireTable\Table;

// ─── Test Models ─────────────────────────────────────────────────────────────

class WtgtInvoice extends Model
{
    protected $table = 'wtgt_invoices';

    protected $guarded = [];

    public function items(): HasMany
    {
        return $this->hasMany(WtgtItem::class, 'invoice_id');
    }
}

class WtgtItem extends Model
{
    protected $table = 'wtgt_items';

    protected $guarded = [];
}

// ─── Test Component ──────────────────────────────────────────────────────────

class WtgtComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtgtInvoice::class)
            ->paginated(false)
            ->columns([
                Column::make('number'),
            ])
            ->filters([
                DateFilter::make('billed_at')->month()->subRows(),
            ])
            ->subRows('items')
            ->subRowColumns([
                Column::make('label'),
                Column::make('amount')
                    ->summarizeSum('Subtotal', scope: 'subRows')
                    ->summarizeSum('Celkem'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wtgt_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->timestamps();
    });

    Schema::create('wtgt_items', function (Blueprint $table) {
        $table->id();
        $table->foreignId('invoice_id');
        $table->string('label');
        $table->integer('amount');
        $table->date('billed_at');
        $table->timestamps();
    });

    $i1 = WtgtInvoice::create(['number' => 'INV-1']);
    WtgtItem::create(['invoice_id' => $i1->id, 'label' => 'May item', 'amount' => 10, 'billed_at' => '2026-05-15']);
    WtgtItem::create(['invoice_id' => $i1->id, 'label' => 'June item A', 'amount' => 20, 'billed_at' => '2026-06-01']);
    WtgtItem::create(['invoice_id' => $i1->id, 'label' => 'June item B', 'amount' => 30, 'billed_at' => '2026-06-20']);

    $i2 = WtgtInvoice::create(['number' => 'INV-2']);
    WtgtItem::create(['invoice_id' => $i2->id, 'label' => 'May only', 'amount' => 40, 'billed_at' => '2026-05-02']);

    $i3 = WtgtInvoice::create(['number' => 'INV-3']);
    WtgtItem::create(['invoice_id' => $i3->id, 'label' => 'June only', 'amount' => 50, 'billed_at' => '2026-06-09']);
});

afterEach(function () {
    Schema::dropIfExists('wtgt_items');
    Schema::dropIfExists('wtgt_invoices');
});

function wtgtComponent(?string $month = null): WtgtComponent
{
    $component = new WtgtComponent;
    $component->mountWithTable();

    if ($month !== null) {
        $component->tableState->set('filters', ['billed_at' => ['value' => $month]]);
    }

    return $component;
}

// ─── Detection ───────────────────────────────────────────────────────────────

it('reports sub-row grand totals so the footer renders without parent summaries', function () {
    $component = wtgtComponent();

    expect($component->tableHasSubRowGrandTotals())->toBeTrue()
        ->and($component->tableHasSummaries())->toBeTrue();
});

it('does not report grand totals when sub-row summaries are subRows-scoped only', function () {
    $component = new WtgtSubtotalsOnlyComponent;
    $component->mountWithTable();

    expect($component->tableHasSubRowGrandTotals())->toBeFalse()
        ->and($component->tableHasSummaries())->toBeFalse();
});

// ─── Grand totals ────────────────────────────────────────────────────────────

it('sums every child across all parents without a filter', function () {
    $totals = wtgtComponent()->computeSubRowGrandTotals();

    expect($totals['amount'][0])->toBe(['label' => 'Celkem', 'value' => 150]);
});

it('sums only filtered children when a sub-row scoped filter is active', function () {
    $totals = wtgtComponent('2026-06')->computeSubRowGrandTotals();

    expect($totals['amount'][0]['value'])->toBe(100);
});

it('excludes subRows-scoped summaries from the grand totals', function () {
    $totals = wtgtComponent()->computeSubRowGrandTotals();

    expect($totals['amount'])->toHaveCount(1);
});

it('respects the page scope by totalling only children of page parents', function () {
    $component = wtgtComponent();
    $component->getTableRecords();

    $totals = $component->computeSubRowGrandTotals('page');

    // Unpaginated table: page == all parents.
    expect($totals['amount'][0]['value'])->toBe(150);
});

it('respects the selection scope', function () {
    $component = wtgtComponent();
    $records = $component->getTableRecords();
    $component->toggleRecordSelection((string) $records->firstWhere('number', 'INV-1')->id);

    $totals = $component->computeSubRowGrandTotals('selection');

    expect($totals['amount'][0]['value'])->toBe(60);
});

class WtgtSubtotalsOnlyComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtgtInvoice::class)
            ->paginated(false)
            ->columns([Column::make('number')])
            ->subRows('items')
            ->subRowColumns([
                Column::make('amount')->summarizeSum('Subtotal', scope: 'subRows'),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}
