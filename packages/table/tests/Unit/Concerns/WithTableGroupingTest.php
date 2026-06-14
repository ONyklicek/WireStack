<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

// ─── Test Model ──────────────────────────────────────────────────────────────

class WtgInvoice extends Model
{
    protected $table = 'wtg_invoices';

    protected $guarded = [];
}

// ─── Test Component ──────────────────────────────────────────────────────────

class WtgComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtgInvoice::class)
            ->paginated(false)
            ->defaultSort('number')
            ->columns([
                Column::make('number')->sortable(),
                Column::make('customer')->sortable(),
                Column::make('total')->summarizeSum('Sum'),
            ])
            ->groupBy('customer');
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wtg_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('customer');
        $table->integer('total');
        $table->timestamps();
    });

    WtgInvoice::create(['number' => 'INV-1', 'customer' => 'Beta', 'total' => 100]);
    WtgInvoice::create(['number' => 'INV-2', 'customer' => 'Acme', 'total' => 250]);
    WtgInvoice::create(['number' => 'INV-3', 'customer' => 'Beta', 'total' => 50]);
    WtgInvoice::create(['number' => 'INV-4', 'customer' => 'Acme', 'total' => 25]);
});

afterEach(function () {
    Schema::dropIfExists('wtg_invoices');
});

function wtgComponent(): WtgComponent
{
    $component = new WtgComponent;
    $component->mountWithTable();

    return $component;
}

// ─── Configuration ───────────────────────────────────────────────────────────

it('rejects relationship paths in groupBy', function () {
    Table::make()->groupBy('customer.name');
})->throws(InvalidArgumentException::class);

it('reports grouping and group summaries', function () {
    $component = wtgComponent();

    expect($component->getTable()->hasGrouping())->toBeTrue()
        ->and($component->tableHasGroupSummaries())->toBeTrue();
});

// ─── Ordering ────────────────────────────────────────────────────────────────

it('keeps groups contiguous by ordering on the group column first', function () {
    $records = wtgComponent()->getTableRecords();

    expect($records->pluck('customer')->all())->toBe(['Acme', 'Acme', 'Beta', 'Beta'])
        // …and the configured sort applies within each group.
        ->and($records->pluck('number')->all())->toBe(['INV-2', 'INV-4', 'INV-1', 'INV-3']);
});

it('lets an explicit sort on the group column control group order', function () {
    $component = wtgComponent();
    $component->tableState->set('sort.column', 'customer');
    $component->tableState->set('sort.direction', 'desc');

    $records = $component->getTableRecords();

    expect($records->pluck('customer')->all())->toBe(['Beta', 'Beta', 'Acme', 'Acme']);
});

// ─── Group values & labels ───────────────────────────────────────────────────

it('resolves group values and default labels', function () {
    $table = wtgComponent()->getTable();
    $record = WtgInvoice::where('customer', 'Acme')->first();

    expect($table->getGroupValue($record))->toBe('Acme')
        ->and($table->resolveGroupLabel($record))->toBe('Acme');
});

it('prefixes string group labels and supports closures', function () {
    $record = WtgInvoice::where('customer', 'Acme')->first();

    $prefixed = Table::make()->model(WtgInvoice::class)->groupBy('customer')->groupLabel('Customer');
    $closure = Table::make()->model(WtgInvoice::class)->groupBy('customer')
        ->groupLabel(fn ($value) => strtoupper((string) $value));

    expect($prefixed->resolveGroupLabel($record))->toBe('Customer: Acme')
        ->and($closure->resolveGroupLabel($record))->toBe('ACME');
});

it('labels empty group values with a dash', function () {
    WtgInvoice::create(['number' => 'INV-5', 'customer' => '', 'total' => 1]);
    $table = wtgComponent()->getTable();

    expect($table->resolveGroupLabel(WtgInvoice::where('number', 'INV-5')->first()))->toBe('—');
});

// ─── Group subtotals ─────────────────────────────────────────────────────────

it('computes per-group subtotals from the group records', function () {
    $component = wtgComponent();
    $component->getTableRecords();

    expect($component->computeGroupSummaries('Acme')['total'][0])->toBe(['label' => 'Sum', 'value' => 275])
        ->and($component->computeGroupSummaries('Beta')['total'][0]['value'])->toBe(150);
});

it('keeps the grand total footer over all groups', function () {
    $component = wtgComponent();
    $component->getTableRecords();

    $summaries = $component->computeTableSummaries('query');

    expect($summaries['total'][0]['value'])->toBe(425);
});

it('skips group subtotals when disabled', function () {
    $component = new WtgNoSubtotalsComponent;
    $component->mountWithTable();

    expect($component->tableHasGroupSummaries())->toBeFalse();
});

class WtgNoSubtotalsComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtgInvoice::class)
            ->paginated(false)
            ->columns([
                Column::make('customer'),
                Column::make('total')->summarizeSum(),
            ])
            ->groupBy('customer')
            ->groupSummaries(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}
