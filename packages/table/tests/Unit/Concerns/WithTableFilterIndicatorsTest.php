<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Filters\NumberRangeFilter;
use NyonCode\WireTable\Filters\SelectFilter;
use NyonCode\WireTable\Table;

// ─── Test Model ──────────────────────────────────────────────────────────────

class WtfiItem extends Model
{
    protected $table = 'wtfi_items';

    protected $guarded = [];
}

// ─── Test Component ──────────────────────────────────────────────────────────

class WtfiComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtfiItem::class)
            ->columns([
                Column::make('name'),
                Column::make('status'),
                Column::make('price'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(['active' => 'Active', 'archived' => 'Archived']),
                NumberRangeFilter::make('price')->label('Price'),
                SelectFilter::make('secret')->options(['a' => 'A'])->hidden(),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    Schema::create('wtfi_items', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('status');
        $table->integer('price');
        $table->timestamps();
    });

    WtfiItem::create(['name' => 'Widget', 'status' => 'active', 'price' => 10]);
    WtfiItem::create(['name' => 'Gadget', 'status' => 'archived', 'price' => 50]);
});

afterEach(function () {
    Schema::dropIfExists('wtfi_items');
});

// ─── Indicators ──────────────────────────────────────────────────────────────

it('has no indicators when no filter is active', function () {
    $component = new WtfiComponent;
    $component->mountWithTable();

    expect($component->getActiveFilterIndicators())->toBe([]);
});

it('returns indicators for active filters keyed by filter name', function () {
    $component = new WtfiComponent;
    $component->mountWithTable();
    $component->tableState->set('filters', [
        'status' => ['value' => 'active'],
        'price' => ['min' => '10', 'max' => ''],
    ]);

    expect($component->getActiveFilterIndicators())->toBe([
        'status' => 'Status: Active',
        'price' => 'Price: ≥ 10',
    ]);
});

it('skips hidden filters even when their state is active', function () {
    $component = new WtfiComponent;
    $component->mountWithTable();
    $component->tableState->set('filters', ['secret' => ['value' => 'a']]);

    expect($component->getActiveFilterIndicators())->toBe([]);
});

it('removes a single filter and keeps the others', function () {
    $component = new WtfiComponent;
    $component->mountWithTable();
    $component->tableState->set('filters', [
        'status' => ['value' => 'active'],
        'price' => ['min' => '10', 'max' => ''],
    ]);

    $component->removeTableFilter('status');

    expect($component->tableState->get('filters'))->toBe(['price' => ['min' => '10', 'max' => '']]);
});

// ─── Rendering ───────────────────────────────────────────────────────────────

it('renders indicator chips for active filters', function () {
    Livewire::test(WtfiComponent::class)
        ->set('tableState.filters.status.value', 'archived')
        ->assertSee('Status: Archived')
        ->assertSee('removeTableFilter');
});

it('removes the chip when the filter is removed via the chip button', function () {
    Livewire::test(WtfiComponent::class)
        ->set('tableState.filters.status.value', 'archived')
        ->assertSee('Status: Archived')
        ->call('removeTableFilter', 'status')
        ->assertDontSee('Status: Archived')
        ->assertSee('Widget');
});

it('shows the reset-all link only with more than one active filter', function () {
    Livewire::test(WtfiComponent::class)
        ->set('tableState.filters.status.value', 'active')
        ->set('tableState.filters.price.min', '5')
        ->assertSee('Status: Active')
        ->assertSee('Price: ≥ 5')
        ->call('resetTableFilters')
        ->assertDontSee('Status: Active');
});
