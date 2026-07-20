<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Whole-system test at the top of the package graph: a reorderable Livewire table
 * (sortable → table → core, all booted by the Integration TestCase) rendered and
 * reordered through the real component lifecycle, asserting both the persisted
 * order column and the re-rendered row order.
 */

class SteTask extends Model
{
    protected $table = 'ste_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

class SteTaskComponent extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return SortableTable::make()
            ->query(SteTask::query()->orderBy('sort_order'))
            ->paginated(false)
            ->columns([TextColumn::make('title')->sortable()])
            ->reorderable('sort_order');
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('ste_tasks', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->integer('sort_order')->default(0);
    });
    SteTask::insert([
        ['id' => 1, 'title' => 'Alpha', 'sort_order' => 1],
        ['id' => 2, 'title' => 'Beta', 'sort_order' => 2],
        ['id' => 3, 'title' => 'Gamma', 'sort_order' => 3],
    ]);
});

afterEach(fn () => Schema::dropIfExists('ste_tasks'));

it('renders a reorderable table and persists a drag-reorder through the lifecycle', function () {
    $component = Livewire::test(SteTaskComponent::class)
        ->assertSee('Alpha')
        ->assertSee('Beta')
        ->assertSee('Gamma')
        // Initial order.
        ->assertSeeInOrder(['Alpha', 'Beta', 'Gamma']);

    // Enter reorder mode and move Gamma to the top (Filament-style drag payload).
    $component
        ->call('toggleReordering')
        ->call('reorderRows', [
            ['value' => 3, 'order' => 1], // Gamma
            ['value' => 1, 'order' => 2], // Alpha
            ['value' => 2, 'order' => 3], // Beta
        ]);

    // Persisted order column.
    expect(SteTask::find(3)->sort_order)->toBe(1)
        ->and(SteTask::find(1)->sort_order)->toBe(2)
        ->and(SteTask::find(2)->sort_order)->toBe(3);

    // A fresh component renders the rows in the new persisted order (defaultSort
    // on sort_order), proving the reorder flows all the way back to the table.
    $reordered = Livewire::test(SteTaskComponent::class);
    $titles = collect($reordered->instance()->getTableRecords())->pluck('title')->all();

    expect($titles)->toBe(['Gamma', 'Alpha', 'Beta']);
});
