<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use NyonCode\WireSortable\Concerns\WithSortable;
use NyonCode\WireSortable\Models\ReorderableColumnOrder;
use NyonCode\WireSortable\SortableTable;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Table;

/*
 * Rco* class names, not something shorter: the full suite loads every test file
 * into one process, so a test class name has to be unique repo-wide — RcComponent
 * was already taken by the table package, and the collision only surfaces in the
 * combined run.
 */

/**
 * The server half of column reordering: what the host does when the client
 * reports a new header order, and what it takes to forget one again.
 *
 * The client half is covered in a browser by verify-column-reorder.mjs. This
 * covers the guards around the write — which matter because the order is
 * persisted per user and a column list arrives from the browser, i.e. from
 * outside.
 */
class RcoTask extends Model
{
    protected $table = 'rco_tasks';

    protected $guarded = [];

    public $timestamps = false;
}

/** Reorderable columns, with a fixed identity so no auth guard is involved. */
class RcoComponent
{
    use WithSortable;

    public mixed $cachedRecords = null;

    protected string $wireTableClass = Table::class;

    public function getTable(): Table
    {
        return SortableTable::make()
            ->query(RcoTask::query())
            ->columnReorderable()
            ->columns([
                TextColumn::make('title'),
                TextColumn::make('status'),
                TextColumn::make('due_at'),
            ]);
    }

    protected function getReorderableUserId(): int|string|null
    {
        return 7;
    }

    protected function getReorderableTableIdentifier(): string
    {
        return 'rco-tasks';
    }
}

/** Same table, but the feature is off. */
class RcoDisabledComponent extends RcoComponent
{
    public function getTable(): Table
    {
        return SortableTable::make()
            ->query(RcoTask::query())
            ->columns([TextColumn::make('title'), TextColumn::make('status')]);
    }
}

/** Reorderable, but nobody is signed in — there is no one to store it for. */
class RcoAnonymousComponent extends RcoComponent
{
    protected function getReorderableUserId(): int|string|null
    {
        return null;
    }
}

beforeEach(function () {
    Schema::create('rco_tasks', function (Blueprint $t) {
        $t->id();
        $t->string('title')->nullable();
        $t->string('status')->nullable();
        $t->timestamp('due_at')->nullable();
    });
});

afterEach(fn () => Schema::dropIfExists('rco_tasks'));

it('stores the new order for this user and table', function () {
    $component = new RcoComponent;

    $component->reorderColumns(['status', 'due_at', 'title']);

    expect(ReorderableColumnOrder::getOrder(7, RcoTask::class, 'rco-tasks'))
        ->toBe(['status', 'due_at', 'title']);
});

it('keeps only columns the table actually defines', function () {
    // The list comes from the browser, so it can name anything at all.
    $component = new RcoComponent;

    $component->reorderColumns(['status', 'password', 'title', '../etc/passwd']);

    expect(ReorderableColumnOrder::getOrder(7, RcoTask::class, 'rco-tasks'))
        ->toBe(['status', 'title']);
});

it('writes nothing when no named column exists', function () {
    // Filtering everything out leaves no order worth recording — and an empty
    // list must not be mistaken for "reset to default".
    $component = new RcoComponent;

    $component->reorderColumns(['nope', 'also-nope']);

    expect(ReorderableColumnOrder::getOrder(7, RcoTask::class, 'rco-tasks'))->toBeNull();
});

it('ignores a reorder on a table that is not reorderable', function () {
    (new RcoDisabledComponent)->reorderColumns(['status', 'title']);

    expect(ReorderableColumnOrder::query()->count())->toBe(0);
});

it('ignores a reorder when there is no user to store it against', function () {
    (new RcoAnonymousComponent)->reorderColumns(['status', 'title']);

    expect(ReorderableColumnOrder::query()->count())->toBe(0);
});

it('overwrites the previous order rather than stacking rows', function () {
    $component = new RcoComponent;

    $component->reorderColumns(['status', 'title']);
    $component->reorderColumns(['title', 'status']);

    expect(ReorderableColumnOrder::getOrder(7, RcoTask::class, 'rco-tasks'))->toBe(['title', 'status'])
        ->and(ReorderableColumnOrder::query()->count())->toBe(1);
});

it('forgets the stored order on reset', function () {
    $component = new RcoComponent;
    $component->reorderColumns(['status', 'title']);

    $component->resetColumnOrder();

    expect(ReorderableColumnOrder::getOrder(7, RcoTask::class, 'rco-tasks'))->toBeNull();
});

it('resets without a stored order, and without a user, rather than throwing', function () {
    (new RcoComponent)->resetColumnOrder();
    (new RcoAnonymousComponent)->resetColumnOrder();
})->throwsNoExceptions();

it('renders the columns in the stored order', function () {
    $component = new RcoComponent;
    $component->reorderColumns(['due_at', 'title', 'status']);

    $names = array_map(fn ($c) => $c->getName(), $component->getReorderableColumns());

    expect($names)->toBe(['due_at', 'title', 'status']);
});

it('falls back to the declared order when nothing is stored', function () {
    $names = array_map(fn ($c) => $c->getName(), (new RcoComponent)->getReorderableColumns());

    expect($names)->toBe(['title', 'status', 'due_at']);
});
