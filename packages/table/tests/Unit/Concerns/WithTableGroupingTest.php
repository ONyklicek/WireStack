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

// ─── Regression: grouping by a date/object-cast column ────────────────────────
// A date-cast column yields a fresh Carbon per record. Comparing the raw value
// with `===` treated every row as its own group; getGroupComparisonKey()
// normalises to a scalar so equal dates bucket together.

class WtgDatedInvoice extends Model
{
    protected $table = 'wtg_invoices';

    protected $guarded = [];

    protected $casts = ['issued_on' => 'date'];
}

class WtgDatedComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtgDatedInvoice::class)
            ->paginated(false)
            ->defaultSort('issued_on')
            ->columns([
                Column::make('issued_on')->sortable(),
                Column::make('total')->summarizeSum('Sum'),
            ])
            ->groupBy('issued_on');
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

it('normalises a date-cast group value to a stable scalar key', function () {
    Schema::table('wtg_invoices', fn (Blueprint $t) => $t->date('issued_on')->nullable());
    WtgDatedInvoice::whereIn('number', ['INV-2', 'INV-4'])->update(['issued_on' => '2026-01-01']);

    $table = (new WtgDatedComponent)->getTable();
    $a = WtgDatedInvoice::where('number', 'INV-2')->first();
    $b = WtgDatedInvoice::where('number', 'INV-4')->first();

    // Two distinct Carbon objects for the same day → equal comparison key…
    expect($a->issued_on)->not->toBe($b->issued_on) // distinct objects
        ->and($table->getGroupComparisonKey($a))->toBe($table->getGroupComparisonKey($b))
        // …while the raw values would fail a strict identity compare.
        ->and($table->getGroupValue($a) === $table->getGroupValue($b))->toBeFalse();
});

it('buckets date-cast rows into one subtotal per day, not per row', function () {
    Schema::table('wtg_invoices', fn (Blueprint $t) => $t->date('issued_on')->nullable());
    // INV-2 (250) and INV-4 (25) share a day; they must subtotal to 275, not each alone.
    WtgDatedInvoice::whereIn('number', ['INV-2', 'INV-4'])->update(['issued_on' => '2026-01-01']);
    WtgDatedInvoice::whereIn('number', ['INV-1', 'INV-3'])->update(['issued_on' => '2026-02-02']);

    $component = new WtgDatedComponent;
    $component->mountWithTable();
    $records = $component->getTableRecords();

    $key = $component->getTable()->getGroupComparisonKey($records->firstWhere('number', 'INV-2'));

    expect($component->computeGroupSummaries($key)['total'][0]['value'])->toBe(275);
});

// Equivalence class: grouping by an enum-cast column. Enum cases are singletons
// so `===` happened to work, but getGroupComparisonKey() normalises them too, so
// the boundary logic is uniform across scalar / date / enum group values.

enum WtgPriority: string
{
    case Low = 'low';
    case High = 'high';
}

class WtgEnumInvoice extends Model
{
    protected $table = 'wtg_invoices';

    protected $guarded = [];

    protected $casts = ['priority' => WtgPriority::class];
}

class WtgEnumComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtgEnumInvoice::class)
            ->paginated(false)
            ->defaultSort('priority')
            ->columns([Column::make('priority')->sortable(), Column::make('total')->summarizeSum('Sum')])
            ->groupBy('priority');
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

it('buckets enum-cast rows by their scalar key into one subtotal per group', function () {
    Schema::table('wtg_invoices', fn (Blueprint $t) => $t->string('priority')->nullable());
    WtgEnumInvoice::whereIn('number', ['INV-2', 'INV-4'])->update(['priority' => 'high']); // 250 + 25
    WtgEnumInvoice::whereIn('number', ['INV-1', 'INV-3'])->update(['priority' => 'low']);

    $component = new WtgEnumComponent;
    $component->mountWithTable();
    $records = $component->getTableRecords();

    $table = $component->getTable();
    $high = $records->firstWhere('number', 'INV-2');

    // Enum case normalises to its scalar value; both high rows share it.
    expect($table->getGroupComparisonKey($high))->toBe('high')
        ->and($component->computeGroupSummaries('high')['total'][0]['value'])->toBe(275);
});
