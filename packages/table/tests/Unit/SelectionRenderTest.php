<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

class SelectionRenderRow extends Model
{
    protected $table = 'selection_render_rows';

    protected $guarded = [];

    public $timestamps = false;
}

class SelectionRenderComponent extends Component
{
    use WithTable;

    public bool $selectable = true;

    public function table(Table $table): Table
    {
        $table
            ->model(SelectionRenderRow::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')]);

        if ($this->selectable) {
            $table->selectable();
        }

        return $table;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class UnselectableRenderComponent extends SelectionRenderComponent
{
    public bool $selectable = false;
}

beforeEach(function () {
    Schema::create('selection_render_rows', function (Blueprint $table) {
        $table->id();
        $table->string('name')->nullable();
    });
    SelectionRenderRow::insert([['name' => 'Alpha'], ['name' => 'Beta']]);
});

afterEach(fn () => Schema::dropIfExists('selection_render_rows'));

it('mounts the selection component exactly once, on the wrapper', function () {
    $html = Livewire::test(SelectionRenderComponent::class)->html();

    // One shared component on the wrapper — the checkboxes, both select-all
    // toggles, the bulk bar and the mobile cards all reach it through
    // data-selection-root; a second mount would fork the state.
    expect(substr_count($html, 'wireRecordSelection('))->toBe(1)
        ->and(substr_count($html, 'data-selection-root'))->toBe(1)
        ->and($html)->toContain("statePath: 'tableState.selection'")
        // The markup/JS contract marker: the packaged record-actions bundle
        // refuses a published view without it instead of mis-selecting ranges.
        ->and($html)->toContain('data-selection-version="1"');
});

it('ships neither the component nor the selection hooks without selectable()', function () {
    Livewire::test(UnselectableRenderComponent::class)
        ->assertDontSee('wireRecordSelection', false)
        ->assertDontSee('data-selection-root', false)
        ->assertDontSee('data-page-keys', false)
        ->assertDontSee('data-select-cell', false);
});

it('marks every selection cell with the data-select-cell hook', function () {
    // The sweep gesture and the widened click target find the selection column
    // by this hook, never by column position — sortable prepends a drag-handle
    // cell and would shift every index.
    $html = Livewire::test(SelectionRenderComponent::class)->html();

    expect(substr_count($html, 'data-select-cell'))->toBe(2); // 2 rows
});

it('keeps the selection assets include on the wrapper, outside the tbody', function () {
    // The tbody is not rendered without visible columns, but the selection is
    // live in the stacked cards too — the include must not depend on it.
    $blade = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/tables/index.blade.php'
    );

    $include = strpos($blade, 'partials.selection-assets');
    $tbody = strpos($blade, '<tbody');

    expect($include)->toBeInt()
        ->and($tbody)->toBeInt()
        ->and($include)->toBeLessThan($tbody);
});
