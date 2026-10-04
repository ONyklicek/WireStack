<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A lazy() table's loadTable() renders, whatever else rode in the same request.
 *
 * Livewire bundles every call made in one tick into one request, and a single
 * skipRender() in it suppresses the render for all of them. A listener that
 * skips its own render — here a status ping the page dispatches on load — used
 * to land in the same request as the placeholder's loadTable(): the state went
 * ready, no markup came back, and the placeholder, which asks only once, stayed
 * up for good. The order inside the request is the client's, so both are pinned.
 */

class LazyArrivalUser extends Model
{
    protected $table = 'lazy_arrival_users';

    protected $guarded = [];
}

class LazyArrivalHost extends Component
{
    use WithTable;

    #[On('status-ping')]
    public function receiveStatus(): void
    {
        $this->skipRender();
    }

    public function table(Table $table): Table
    {
        return $table
            ->model(LazyArrivalUser::class)
            ->columns([TextColumn::make('name')])
            ->lazy()
            ->paginated(false);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

/** The call a `Livewire.dispatch('status-ping')` puts in the request. */
function lazyArrivalPing(): array
{
    return ['method' => '__dispatch', 'params' => ['status-ping', []], 'path' => ''];
}

/** The call the placeholder's `x-intersect` puts in the request. */
function lazyArrivalLoad(): array
{
    return ['method' => 'loadTable', 'params' => [], 'path' => ''];
}

beforeEach(function () {
    Schema::create('lazy_arrival_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
    });

    LazyArrivalUser::create(['name' => 'Ada Lovelace']);
});

afterEach(fn () => Schema::dropIfExists('lazy_arrival_users'));

it('renders the table when a render-skipping listener ran earlier in the request', function () {
    Livewire::test(LazyArrivalHost::class)
        ->assertSee('table-lazy-wrapper', escape: false)
        ->update(calls: [lazyArrivalPing(), lazyArrivalLoad()])
        ->assertDontSee('table-lazy-wrapper', escape: false)
        ->assertSee('Ada Lovelace');
});

it('renders the table when a render-skipping listener runs later in the request', function () {
    Livewire::test(LazyArrivalHost::class)
        ->update(calls: [lazyArrivalLoad(), lazyArrivalPing()])
        ->assertDontSee('table-lazy-wrapper', escape: false)
        ->assertSee('Ada Lovelace');
});

it('leaves a listener alone to skip its render when the table is not arriving', function () {
    $component = Livewire::test(LazyArrivalHost::class)->call('loadTable');

    expect($component->effects['html'] ?? null)->not->toBeNull();

    // The forced render belongs to the arrival alone: a later ping on its own
    // still gets the skip it asked for, and the response carries no markup.
    $component->update(calls: [lazyArrivalPing()]);

    expect($component->effects['html'] ?? null)->toBeNull();
});
