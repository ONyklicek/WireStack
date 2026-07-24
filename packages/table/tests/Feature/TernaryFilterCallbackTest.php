<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\TernaryFilter;
use NyonCode\WireTable\Table;

/*
 * End-to-end guard for the ternary ->query() contract: the select posts the
 * option key ('true'/'false') into tableState, and the callback must still see
 * a real bool by the time it branches. Exercised through the component rather
 * than the filter, because the panel path reaches the callback via
 * TableQueryService — not via Filter::apply() — and that is exactly where the
 * normalization used to be missing.
 */
class TfcInvoice extends Model
{
    protected $table = 'tfc_invoices';

    protected $guarded = [];

    public $timestamps = false;
}

class TfcComponent extends Component
{
    use WithTable;

    /** @var array<int, mixed> */
    public static array $seen = [];

    public function table(Table $table): Table
    {
        return $table
            ->model(TfcInvoice::class)
            ->columns([TextColumn::make('number')])
            ->filters([
                TernaryFilter::make('invoiced')
                    ->query(function (Builder $query, $value) {
                        static::$seen[] = $value;

                        return $value
                            ? $query->whereNotNull('invoice_number')
                            : $query->whereNull('invoice_number');
                    }),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    TfcComponent::$seen = [];

    Schema::create('tfc_invoices', function (Blueprint $table) {
        $table->id();
        $table->string('number');
        $table->string('invoice_number')->nullable();
    });

    TfcInvoice::insert([
        ['id' => 1, 'number' => 'A', 'invoice_number' => 'INV-1'],
        ['id' => 2, 'number' => 'B', 'invoice_number' => null],
    ]);
});

afterEach(function () {
    Schema::dropIfExists('tfc_invoices');
});

it('applies the "false" option through the callback as a real bool', function () {
    $component = Livewire::test(TfcComponent::class)
        ->set('tableState.filters.invoiced.value', 'false');

    $rows = $component->instance()->getTableRecords();

    expect(TfcComponent::$seen)->each->toBeBool()
        ->and(TfcComponent::$seen)->toContain(false)
        ->and(TfcComponent::$seen)->not->toContain(true)
        ->and($rows->pluck('number')->all())->toBe(['B']);
});

it('applies the "true" option through the callback as a real bool', function () {
    $component = Livewire::test(TfcComponent::class)
        ->set('tableState.filters.invoiced.value', 'true');

    $rows = $component->instance()->getTableRecords();

    expect(TfcComponent::$seen)->toContain(true)
        ->and(TfcComponent::$seen)->not->toContain(false)
        ->and($rows->pluck('number')->all())->toBe(['A']);
});

it('leaves the table unfiltered when the "All" placeholder is picked', function () {
    $component = Livewire::test(TfcComponent::class)
        ->set('tableState.filters.invoiced.value', 'false')
        ->set('tableState.filters.invoiced.value', '');

    $rows = $component->instance()->getTableRecords();

    expect($rows->pluck('number')->all())->toBe(['A', 'B']);
});
