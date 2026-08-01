<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Services\WriteGeneration;
use NyonCode\WireTable\Table;

/*
 * A table with cacheQuery() serves a paginated *slice*, and the slice is shaped
 * by state the SQL never sees (perPage and the page are applied inside the
 * cache callback) or that a caller-supplied key cannot know about (sort,
 * search, filters). Miss any of them in the key and the table freezes for the
 * whole TTL: the per-page select does nothing until you happen to change page,
 * which shifts the key and finally forces a real query.
 */
class PpPost extends Model
{
    protected $table = 'pp_posts';

    protected $guarded = [];
}

class PpComponent extends Component
{
    use WithTable;

    public ?string $cacheKey = null;

    public bool $cached = false;

    public bool $poll = false;

    public bool $paginate = true;

    public bool $editable = false;

    /** Test probe: the poll skip decision is protected on the trait. */
    public function pollWouldSkipRender(): bool
    {
        return $this->shouldSkipPollRender();
    }

    public function table(Table $table): Table
    {
        $t = $table
            ->model(PpPost::class)
            ->paginated($this->paginate)
            ->perPage(2)
            ->perPageOptions([2, 5, 10])
            ->columns([$this->editable
                ? TextInputColumn::make('title')->sortable()->searchable()
                : TextColumn::make('title')->sortable()->searchable()]);

        if ($this->cached) {
            $t->cacheQuery(600, $this->cacheKey);
        }

        if ($this->poll) {
            $t->poll('2s')->pollChangeDetection();
        }

        return $t;
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('cache.default', 'array');

    Schema::create('pp_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->timestamps();
    });

    for ($i = 1; $i <= 6; $i++) {
        PpPost::create(['id' => $i, 'title' => 'T'.$i]);
    }
});

afterEach(function () {
    Schema::dropIfExists('pp_posts');
});

it('applies a per-page change on the same request', function () {
    $c = Livewire::test(PpComponent::class);

    expect($c->instance()->getTableRecords()->count())->toBe(2);

    $c->set('tableState.pagination.perPage', 5);

    expect($c->instance()->getTableRecords()->count())->toBe(5);
});

it('applies a per-page change immediately on a cached table', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true]);

    expect($c->instance()->getTableRecords()->count())->toBe(2);

    $c->set('tableState.pagination.perPage', 5);

    expect($c->instance()->getTableRecords()->count())->toBe(5);
});

it('applies a per-page change immediately under a caller-supplied cache key', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true, 'cacheKey' => 'posts-report']);

    expect($c->instance()->getTableRecords()->count())->toBe(2);

    $c->set('tableState.pagination.perPage', 5);

    expect($c->instance()->getTableRecords()->count())->toBe(5);
});

it('keeps sort and search live under a caller-supplied cache key', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true, 'cacheKey' => 'posts-report']);

    expect($c->instance()->getTableRecords()->first()->title)->toBe('T1');

    $c->set('tableState.sort.column', 'title')->set('tableState.sort.direction', 'desc');
    expect($c->instance()->getTableRecords()->first()->title)->toBe('T6');

    $c->set('tableState.search', 'T6');
    expect($c->instance()->getTableRecords()->total())->toBe(1);
});

it('still caches: a second read of the same view does not re-query', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true, 'cacheKey' => 'posts-report']);

    expect($c->instance()->getTableRecords()->total())->toBe(6);

    // Row added behind the cache — the identical view must still serve the
    // cached payload, otherwise cacheQuery() would be doing nothing.
    PpPost::create(['id' => 7, 'title' => 'T7']);
    $c->call('$refresh');

    expect($c->instance()->getTableRecords()->total())->toBe(6);
});

it('caches an unpaginated table without a page in the key', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true, 'paginate' => false]);

    expect($c->instance()->getTableRecords())->toHaveCount(6);

    PpPost::create(['id' => 7, 'title' => 'T7']);
    $c->call('$refresh');

    expect($c->instance()->getTableRecords())->toHaveCount(6);
});

it('normalises the per-page value the select posts back to an int', function () {
    $c = Livewire::test(PpComponent::class);

    $c->set('tableState.pagination.perPage', '5');

    expect($c->instance()->tableState->get('pagination.perPage'))->toBe(5);
});

it('falls back to the configured page size when the client sends one that is not offered', function () {
    $c = Livewire::test(PpComponent::class);

    // A crafted payload asking for the whole table in one page.
    $c->set('tableState.pagination.perPage', 500000);
    expect($c->instance()->tableState->get('pagination.perPage'))->toBe(2);

    $c->set('tableState.pagination.perPage', 'all');
    expect($c->instance()->tableState->get('pagination.perPage'))->toBe(2);
});

it('does not let a poll in the same request swallow the render a per-page change needs', function () {
    $c = Livewire::test(PpComponent::class, ['poll' => true]);

    // Baseline checksum, so the next poll would otherwise report "unchanged".
    $c->call('refreshTable');
    expect($c->instance()->pollWouldSkipRender())->toBeTrue();

    // Livewire pools commits fired in the same tick: the user's select change
    // and a poll tick can land in one request.
    $c->set('tableState.pagination.perPage', 5);

    expect($c->instance()->pollWouldSkipRender())->toBeFalse()
        ->and($c->instance()->getTableRecords()->count())->toBe(5);
});

it('still skips the poll render when nothing changed at all', function () {
    $c = Livewire::test(PpComponent::class, ['poll' => true]);

    $c->call('refreshTable');

    expect($c->instance()->pollWouldSkipRender())->toBeTrue();
});

/*
 * Writing to a cached table.
 *
 * A cached slice cannot be found and deleted after a write: the namespace comes
 * from the SQL, so every filter, search term and sort a user ever opened has its
 * own entry and the writer knows none of them. So the key carries a generation
 * instead, and a write bumps it — the stale entries are not deleted, they stop
 * being addressed.
 *
 * Without this, cacheQuery() and writing were quietly incompatible: the edit
 * committed, the notification said so, and every user kept being served the
 * pre-write rows until the TTL ran out.
 */
it('serves fresh rows to everyone after an inline edit on a cached table', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true, 'editable' => true]);

    expect($c->instance()->getTableRecords()->first()->title)->toBe('T1');

    $c->call('updateTableCell', 1, 'title', 'Edited');

    // A second component is the point: it shares the cache store, not the
    // request. It is the "other user" the entry would otherwise be stale for.
    expect(Livewire::test(PpComponent::class, ['cached' => true, 'editable' => true])
        ->instance()->getTableRecords()->first()->title)->toBe('Edited');
});

it('serves fresh rows after an action writes to a cached table', function () {
    $c = Livewire::test(PpComponent::class, ['cached' => true]);

    expect($c->instance()->getTableRecords()->total())->toBe(6);

    PpPost::create(['id' => 7, 'title' => 'T7']);
    $c->call('invalidateTable');

    expect(Livewire::test(PpComponent::class, ['cached' => true])
        ->instance()->getTableRecords()->total())->toBe(7);
});

it('retires every cached view of the table, not only the one that was on screen', function () {
    // The write happens while a filter is applied; the unfiltered view was cached
    // under a different namespace entirely and must be retired too.
    $filtered = Livewire::test(PpComponent::class, ['cached' => true, 'editable' => true]);
    $filtered->set('tableState.search', 'T1');
    expect($filtered->instance()->getTableRecords()->total())->toBe(1);

    $all = Livewire::test(PpComponent::class, ['cached' => true, 'editable' => true]);
    expect($all->instance()->getTableRecords()->first()->title)->toBe('T1');

    $filtered->call('updateTableCell', 1, 'title', 'Renamed');

    expect(Livewire::test(PpComponent::class, ['cached' => true, 'editable' => true])
        ->instance()->getTableRecords()->first()->title)->toBe('Renamed');
});

it('records nothing for a table that neither caches nor polls', function () {
    // The write generation has exactly two readers — the cache key and poll
    // change detection — and moving it is a write to the shared cache store. A
    // table with neither has nobody to tell.
    $c = Livewire::test(PpComponent::class);

    $c->call('invalidateTable');

    expect(app(WriteGeneration::class)->current(PpPost::class))->toBe(0);
});
