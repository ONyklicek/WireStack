<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * Reorder mode and the ordinary table controls.
 *
 * Reorder mode drops pagination and the user's sort, because a drag needs the
 * list whole and in its canonical order. It used to drop search and filters
 * with them, by fetching the bare base query — which left `alwaysReorderable()`
 * tables rendering a search box that could never do anything, since those are
 * in reorder mode for good.
 *
 * The reason search was dropped is the write, not the read: the client sends
 * each row's new position as `1..n`, so a drag inside a narrowed list would
 * have stamped those positions over the whole table, moving rows the user could
 * not even see. So the read only became safe once the write stopped renumbering
 * from 1 and started redistributing the slots the dragged rows already own.
 */
class RsTask extends Model
{
    protected $table = 'rs_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

/** Permanently in reorder mode — the shape that has no way back to a plain table. */
class RsAlwaysHost extends Component
{
    use WithSortable;
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(RsTask::class)
            ->alwaysReorderable('sort_order')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('team')->label('Team'),
            ])
            ->defaultSort('sort_order')
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** Scoped to one team, so a drag can never reach the rows of the other. */
class RsScopedHost
{
    use WithSortable;

    public mixed $cachedRecords = null;

    public function __construct()
    {
        $this->isReordering = true;
    }

    public function getTable(): Table
    {
        return Table::make()
            ->query(RsTask::where('team', 'alpha'))
            ->reorderable('sort_order');
    }
}

beforeEach(function () {
    Schema::create('rs_tasks', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->string('team')->default('alpha');
        $t->integer('sort_order')->nullable();
    });

    RsTask::insert([
        ['id' => 1, 'title' => 'Draft onboarding checklist', 'team' => 'alpha', 'sort_order' => 10],
        ['id' => 2, 'title' => 'Review bulk action copy', 'team' => 'alpha', 'sort_order' => 20],
        ['id' => 3, 'title' => 'Verify audit event payloads', 'team' => 'alpha', 'sort_order' => 30],
        ['id' => 4, 'title' => 'Ship audit migration notes', 'team' => 'alpha', 'sort_order' => 40],
        ['id' => 5, 'title' => 'Foreign team task', 'team' => 'beta', 'sort_order' => 50],
    ]);
});

afterEach(fn () => Schema::dropIfExists('rs_tasks'));

// ── The read ────────────────────────────────────────────────

it('narrows a permanently reorderable table by the search term', function () {
    Livewire::test(RsAlwaysHost::class)
        ->set('tableState.search', 'audit')
        ->assertSee('Verify audit event payloads')
        ->assertSee('Ship audit migration notes')
        ->assertDontSee('Draft onboarding checklist');
});

it('still shows every row when nothing is searched for', function () {
    Livewire::test(RsAlwaysHost::class)
        ->assertSee('Draft onboarding checklist')
        ->assertSee('Verify audit event payloads');
});

it('orders by the drag column rather than the sort the table was given', function () {
    // A header sort has to give way: the sequence on screen is the sequence the
    // drop writes back, so it can only ever be the order column's.
    $records = Livewire::test(RsAlwaysHost::class)
        ->set('tableState.sort', ['column' => 'title', 'direction' => 'desc'])
        ->instance()
        ->getTableRecords();

    expect($records->pluck('id')->all())->toBe([1, 2, 3, 4, 5]);
});

// ── The write ───────────────────────────────────────────────

it('redistributes the slots the dragged rows already held', function () {
    // Rows 1 and 2 own slots 10 and 20. Swapping them must reuse those two
    // values, not renumber the pair to 1 and 2 and jump them up the table.
    $component = new RsScopedHost;

    $component->reorderRows([
        ['value' => 2, 'order' => 1],
        ['value' => 1, 'order' => 2],
    ]);

    expect(RsTask::find(2)->sort_order)->toBe(10)
        ->and(RsTask::find(1)->sort_order)->toBe(20);
});

it('leaves rows the search hid exactly where they were', function () {
    // The two "audit" rows are all that a searching user can drag. Swapping them
    // may only exchange slots 30 and 40 between themselves — every other row
    // keeps the position it had, including the ones above them.
    $component = new RsScopedHost;

    $component->reorderRows([
        ['value' => 4, 'order' => 1],
        ['value' => 3, 'order' => 2],
    ]);

    expect(RsTask::find(4)->sort_order)->toBe(30)
        ->and(RsTask::find(3)->sort_order)->toBe(40)
        ->and(RsTask::find(1)->sort_order)->toBe(10)
        ->and(RsTask::find(2)->sort_order)->toBe(20);
});

it('never writes a row outside the table query, even one dragged alongside real rows', function () {
    $component = new RsScopedHost;

    $component->reorderRows([
        ['value' => 5, 'order' => 1],
        ['value' => 2, 'order' => 2],
        ['value' => 1, 'order' => 3],
    ]);

    // The foreign row brings no slot with it and drops out of the write; the two
    // in-scope rows still swap the slots they owned between them.
    expect(RsTask::find(5)->sort_order)->toBe(50)
        ->and(RsTask::find(2)->sort_order)->toBe(10)
        ->and(RsTask::find(1)->sort_order)->toBe(20);
});

it('falls back to the sent positions when the order column holds nothing to reuse', function () {
    // A column that was never populated has no slots to redistribute, so the
    // client's own positions are all the ordering there is.
    RsTask::whereIn('id', [1, 2])->update(['sort_order' => null]);

    $component = new RsScopedHost;

    $component->reorderRows([
        ['value' => 2, 'order' => 1],
        ['value' => 1, 'order' => 2],
    ]);

    expect(RsTask::find(2)->sort_order)->toBe(1)
        ->and(RsTask::find(1)->sort_order)->toBe(2);
});
