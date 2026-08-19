<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\WireSortableServiceProvider;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * What one drop costs.
 *
 * The client collected EVERY `tr[wire:key]` in the tbody and sent the lot, so a
 * drop paid one UPDATE per row on the page regardless of how far anything moved
 * — and `alwaysReorderable()` drops pagination, so "the page" can be the whole
 * table.
 *
 * The fix rests on a property of `resolveReorderSlots()` that was already there:
 * the dragged rows keep the set of slots they already occupied — their existing
 * order values, sorted, handed back out in the new visual sequence — and rows
 * that were not sent keep the positions they had. That makes the algorithm
 * correct for ANY contiguous subset, which is why the client can send the moved
 * range alone and get byte-identical ordering out.
 */
class RwTask extends Model
{
    protected $table = 'rw_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

class RwHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RwTask::class)
            ->alwaysReorderable('sort_order')
            ->columns([TextColumn::make('title')])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

function rwSeed(int $rows): void
{
    RwTask::insert(array_map(fn (int $i) => [
        'id' => $i,
        'title' => 'Task '.$i,
        'sort_order' => $i,
    ], range(1, $rows)));
}

/** The order column, keyed by id, as the table would read it. */
function rwOrder(): array
{
    return RwTask::query()->orderBy('id')->pluck('sort_order', 'id')->all();
}

/**
 * Replay a drop and count only what the WRITE costs.
 *
 * The component is mounted before the listener goes on, so the table's own
 * render queries are not charged to the reorder.
 *
 * @param  array<int, int>  $keysInNewOrder
 */
function rwDrop(array $keysInNewOrder): int
{
    $component = Livewire::test(RwHost::class);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $component->call('reorderRows', array_map(
        fn (int $key, int $i) => ['value' => (string) $key, 'order' => $i + 1],
        $keysInNewOrder,
        array_keys($keysInNewOrder),
    ));

    return $queries;
}

beforeEach(function () {
    Schema::create('rw_tasks', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->integer('sort_order');
    });
});

afterEach(function () {
    Schema::dropIfExists('rw_tasks');
});

it('costs one query per row it is SENT — which is why the payload is the cost', function () {
    // A slope, not an absolute: every drop also pays a slot lookup and the
    // re-read that follows the write, and those are constant. What matters is
    // that sending the whole tbody makes the write scale with the TABLE, not
    // with the distance anything moved.
    rwSeed(20);
    $small = rwDrop(range(1, 20));

    RwTask::query()->delete();
    rwSeed(60);
    $large = rwDrop(range(1, 60));

    expect(($large - $small) / 40)->toEqual(1.0);
});

it('costs nothing extra per row when it is sent only the moved range', function () {
    // The same drag — one row moved three places — on a table of 20 and a table
    // of 60. A range payload is the same size either way, so the write is too.
    rwSeed(20);
    $small = rwDrop([4, 5, 6, 3]);

    RwTask::query()->delete();
    rwSeed(60);
    $large = rwDrop([4, 5, 6, 3]);

    expect($large - $small)->toBe(0);
});

it('gives the same ordering whether it is sent the whole page or only the moved range', function () {
    // The property the client-side fix rests on, and the reason it is safe:
    // `resolveReorderSlots()` redistributes the dragged rows' OWN existing order
    // values among themselves, so a contiguous subset lands exactly where the
    // full payload would have put it.
    rwSeed(10);

    rwDrop([1, 2, 4, 5, 6, 3, 7, 8, 9, 10]);
    $fromWholePage = rwOrder();

    RwTask::query()->delete();
    rwSeed(10);

    rwDrop([4, 5, 6, 3]);

    expect(rwOrder())->toBe($fromWholePage);
});

it('leaves rows outside the range exactly where they were', function () {
    rwSeed(10);

    rwDrop([4, 5, 6, 3]);

    $order = rwOrder();

    expect($order[1])->toBe(1)
        ->and($order[2])->toBe(2)
        ->and($order[7])->toBe(7)
        ->and($order[10])->toBe(10)
        // …while the moved range redistributed its own slots.
        ->and($order[4])->toBe(3)
        ->and($order[5])->toBe(4)
        ->and($order[6])->toBe(5)
        ->and($order[3])->toBe(6);
});

it('ships a drag controller that sends the range rather than the page', function () {
    // The other half lives in the browser, and the shipped bundle is where a
    // `resources/js` edit that was never rebuilt shows up.
    $bundle = WireSortableServiceProvider::ASSETS_PATH.'/wire-sortable.js';

    expect(is_file($bundle))->toBeTrue()
        ->and(file_get_contents($bundle))
        ->toContain('reorderPayload')
        ->toContain('orderBeforeDrag');
});
