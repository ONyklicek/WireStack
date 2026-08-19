<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Sub-rows are batched for the page — unless the table is reordering.
 *
 * `WithTable::getTableRecords()` lets a plugin trait intercept the record fetch,
 * which is how reorder mode returns its own unpaginated set. That intercept
 * returned before `eagerLoadSubRows()` ran, so a sub-row table in reorder mode
 * silently fell back to one query per parent — the N+1 the eager load exists to
 * remove, on the mode that also drops pagination and therefore has the most rows
 * on the page.
 *
 * Measured as a slope: the number of queries a render costs must not grow with
 * the number of parents.
 */
class RsOrder extends Model
{
    protected $table = 'rs_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function lines(): HasMany
    {
        return $this->hasMany(RsLine::class, 'order_id');
    }
}

class RsLine extends Model
{
    protected $table = 'rs_lines';

    protected $guarded = [];

    public $timestamps = false;
}

class RsHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RsOrder::class)
            ->alwaysReorderable('sort_order')
            ->columns([TextColumn::make('reference')])
            ->subRows('lines')
            ->subRowColumns([TextColumn::make('label')])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function rsSeed(int $orders): void
{
    $first = (int) (RsOrder::max('id') ?? 0);

    RsOrder::insert(array_map(fn (int $i) => [
        'reference' => 'ORD-'.$i,
        'sort_order' => $i,
    ], range(1, $orders)));

    RsLine::insert(array_map(fn (int $i) => [
        'order_id' => $first + $i,
        'label' => 'Line '.$i,
    ], range(1, $orders)));
}

/**
 * Queries one render costs, with every parent expanded.
 *
 * Expansion happens first and is not counted: what is being measured is the
 * render that follows, where the page needs every parent's lines — the shape the
 * eager load exists for. `expandAllRows()` cannot run from `mount()`, which is
 * before `$tableState` exists.
 */
function rsRenderQueries(): int
{
    $component = Livewire::test(RsHost::class);
    $component->call('expandAllRows');

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $component->call('$refresh');

    return $queries;
}

beforeEach(function () {
    Schema::create('rs_orders', function (Blueprint $table) {
        $table->id();
        $table->string('reference');
        $table->integer('sort_order');
    });

    Schema::create('rs_lines', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('order_id');
        $table->string('label');
    });
});

afterEach(function () {
    Schema::dropIfExists('rs_lines');
    Schema::dropIfExists('rs_orders');
});

it('batches sub-rows for the page while reordering, instead of going per parent', function () {
    rsSeed(4);
    $small = rsRenderQueries();

    rsSeed(12); // 4 → 16 parents
    $large = rsRenderQueries();

    // The eager load is one query for the whole page, so twelve more parents cost
    // no more queries. Without it each parent fetches its own lines.
    expect($large - $small)->toBe(0);
});
