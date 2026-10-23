<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A poll tick refreshes the data and nothing else.
 *
 * The toolbar, the filter panels, the modals and every floating panel opened from
 * them belong to the user while they are using them. A tick that re-rendered the
 * whole component morphed all of it: an open date picker in a row's edit modal
 * lost its Floating UI position on every tick and dropped into the top-left
 * corner of the page. So a tick answers with rows, or with the data region's
 * island, and never with the component — unless something else rides in the same
 * request and needs that render itself.
 */
class PkcRow extends Model
{
    protected $table = 'pkc_rows';

    protected $guarded = [];
}

class PkcHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(PkcRow::class)
            ->poll('2s')
            ->selectable()
            ->paginated()
            ->perPage(5)
            ->perPageOptions([5, 10])
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('note')
                    ->label('Note')
                    ->form([TextInput::make('note')->live()])
                    ->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('pkc_rows', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });

    PkcRow::create(['id' => 1, 'name' => 'First']);
    PkcRow::create(['id' => 2, 'name' => 'Second']);
});

afterEach(fn () => Schema::dropIfExists('pkc_rows'));

/**
 * One request carrying property updates and calls together — what the browser
 * sends when Livewire pools a tick with something the user did.
 *
 * @param  array<string, mixed>  $updates
 * @param  list<string>  $methods
 * @return array<string, mixed>
 */
function pkcCommit(array $snapshot, array $updates, array $methods): array
{
    $response = test()->withHeader('X-Livewire', 'true')->postJson(
        app('livewire')->getUpdateUri(),
        ['components' => [[
            'snapshot' => json_encode($snapshot),
            'updates' => $updates,
            'calls' => array_map(
                fn (string $method) => ['method' => $method, 'params' => [], 'path' => '', 'id' => (string) random_int(1, 99999)],
                $methods,
            ),
        ]]],
    );

    $response->assertOk();

    return $response->json('components.0.effects');
}

it('answers a tick with the data region alone', function (Closure $change, string $expect) {
    $test = Livewire::test(PkcHost::class);

    $change();

    $test->call('refreshTable');

    $fragments = implode('', $test->effects['islandFragments'] ?? []);

    expect($test->effects['html'] ?? null)->toBeNull()
        ->and($fragments)->toContain($expect);
})->with([
    'a row changed' => [fn () => PkcRow::find(2)->update(['name' => 'Renamed']), 'Renamed'],
    'a row arrived' => [fn () => PkcRow::create(['id' => 3, 'name' => 'Third']), 'Third'],
]);

it('carries the selection\'s page keys and count inside the region', function () {
    // The selection controller sits on the wrapper, outside the island. Without
    // a copy inside the region, a tick that brought a row would leave "select
    // the page" and "select all N" counting the rows from before it.
    $test = Livewire::test(PkcHost::class);

    PkcRow::create(['id' => 3, 'name' => 'Third']);

    $test->call('refreshTable');

    $fragments = implode('', $test->effects['islandFragments'] ?? []);

    expect($fragments)->toContain('data-selection-sync')
        ->and($fragments)->toContain('data-matching="3"')
        ->and($fragments)->toContain(e(json_encode(['1', '2', '3'])));
});

it('leaves an open modal alone entirely', function () {
    $test = Livewire::test(PkcHost::class)->call('openActionModal', '1', 'note');

    PkcRow::find(2)->update(['name' => 'Renamed']);

    $test->call('refreshTable');

    expect($test->effects['html'] ?? null)->toBeNull()
        ->and($test->effects['islandFragments'] ?? null)->toBeNull();
});

it('renders as usual when a tick shares the request with something else', function (array $updates, array $methods) {
    // Livewire pools a tick with whatever the user queued in the same moment, and
    // decides the skip after the last call — so a skip from the tick would swallow
    // the render the other half needs.
    $test = Livewire::test(PkcHost::class)->call('openActionModal', '1', 'note');

    $effects = pkcCommit($test->snapshot, $updates, $methods);

    expect($effects['html'] ?? null)->not->toBeNull();
})->with([
    'a field typed in the modal' => [['tableState.modal.actions.0.data.note' => 'typed'], ['refreshTable']],
    'a page size picked' => [['tableState.pagination.perPage' => '10'], ['refreshTable']],
]);
