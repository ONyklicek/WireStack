<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Support\SubRowKey;
use NyonCode\WireTable\Table;

/*
 * A sub-row action runs against its child.
 *
 * `subRowActions()` used to render buttons that called a bare
 * `mountAction('name')` — a method a table host does not have — so an action on
 * a child row drew and did nothing. The buttons now call the row action entry
 * points under a SubRowKey (parent + child), and the host finds the child
 * through a parent the table's own query returns.
 */
class SraOrder extends Model
{
    protected $table = 'sra_orders';

    protected $guarded = [];

    public $timestamps = false;

    public function lines(): HasMany
    {
        return $this->hasMany(SraLine::class, 'order_id');
    }
}

class SraLine extends Model
{
    protected $table = 'sra_lines';

    protected $guarded = [];

    public $timestamps = false;
}

class SraHost extends Component
{
    use WithTable;

    /** @var list<string> */
    public array $log = [];

    public function table(Table $table): Table
    {
        return $table
            // Order 3 is outside the table's query: its lines must be out of reach.
            ->query(SraOrder::query()->whereKeyNot(3))
            ->columns([TextColumn::make('name')])
            ->rowInactive(fn (SraOrder $order) => $order->name === 'Locked', fn ($row) => $row->actions(false))
            ->actions([
                Action::make('touch')->action(function (Model $record) {
                    $this->log[] = 'row:'.$record::class.':'.$record->getKey();
                }),
                Action::make('rowOnly')->action(function (Model $record) {
                    $this->log[] = 'rowOnly:'.$record->getKey();
                }),
            ])
            ->subRows('lines')
            ->subRowColumns([TextColumn::make('label')])
            ->subRowActions([
                Action::make('touch')->action(function (Model $record) {
                    $this->log[] = 'sub:'.$record::class.':'.$record->getKey();
                }),
                Action::make('rename')
                    ->form([TextInput::make('label')->required()])
                    ->action(fn (SraLine $record, array $data) => $record->update(['label' => $data['label']])),
                Action::make('guarded')->action(function (bool $confirmed, callable $halt, SraLine $record) {
                    if (! $confirmed) {
                        return $halt()->heading('Sure about this line?');
                    }

                    $this->log[] = 'halted:'.$record->getKey();
                }),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::create('sra_orders', function (Blueprint $t) {
        $t->id();
        $t->string('name');
    });
    Schema::create('sra_lines', function (Blueprint $t) {
        $t->id();
        $t->foreignId('order_id');
        $t->string('label');
    });

    SraOrder::create(['id' => 1, 'name' => 'First']);
    SraOrder::create(['id' => 2, 'name' => 'Locked']);
    SraOrder::create(['id' => 3, 'name' => 'Hidden']);

    // Line ids deliberately overlap order ids, so a child looked up among the
    // parents would hit the wrong record instead of none.
    SraLine::create(['id' => 1, 'order_id' => 1, 'label' => 'One']);
    SraLine::create(['id' => 2, 'order_id' => 2, 'label' => 'Two']);
    SraLine::create(['id' => 3, 'order_id' => 3, 'label' => 'Three']);
});

afterEach(function () {
    Schema::dropIfExists('sra_lines');
    Schema::dropIfExists('sra_orders');
});

it('points a sub-row button at the row action entry points under a sub-row key', function () {
    $html = Livewire::test(SraHost::class)
        ->call('toggleRowExpansion', '1')
        ->html();

    expect($html)->toContain('executeTableAction(&#039;1~sub~1&#039;, &#039;touch&#039;)')
        ->toContain('openActionModal(&#039;1~sub~1&#039;, &#039;rename&#039;)')
        ->not->toContain('mountAction(&#039;touch&#039;)');
});

it('runs the sub-row action against the child, and the row action against the row', function () {
    Livewire::test(SraHost::class)
        ->call('executeTableAction', SubRowKey::encode(1, 1), 'touch')
        ->call('executeTableAction', '1', 'touch')
        ->assertSet('log', ['sub:'.SraLine::class.':1', 'row:'.SraOrder::class.':1']);
});

it('opens a sub-row action modal and saves the child from it', function () {
    Livewire::test(SraHost::class)
        ->call('openActionModal', SubRowKey::encode(1, 1), 'rename')
        ->set('tableState.modal.actions.0.data.label', 'Renamed')
        ->call('submitActionModal');

    expect(SraLine::find(1)->label)->toBe('Renamed')
        ->and(SraOrder::find(1)->name)->toBe('First');
});

it('confirms a halted sub-row action against the child', function () {
    Livewire::test(SraHost::class)
        ->call('executeTableAction', SubRowKey::encode(1, 1), 'guarded')
        ->assertSet('tableState.modal.halt.show', true)
        ->call('submitHaltModal')
        ->assertSet('log', ['halted:1']);
});

it('refuses a sub-row key the table cannot reach', function (string $key) {
    Livewire::test(SraHost::class)
        ->call('executeTableAction', $key, 'touch')
        ->call('openActionModal', $key, 'rename')
        ->assertSet('log', [])
        ->assertSet('tableState.modal.actions', []);
})->with([
    'a line of an order outside the query' => [SubRowKey::encode(3, 3)],
    'a line under another order' => [SubRowKey::encode(1, 3)],
    'an unknown line' => [SubRowKey::encode(1, 99)],
    'a locked parent' => [SubRowKey::encode(2, 2)],
]);

it('does not run a row action for a sub-row key, nor a sub-row action for a row key', function () {
    Livewire::test(SraHost::class)
        ->call('executeTableAction', '1', 'guarded')
        ->call('executeTableAction', SubRowKey::encode(1, 1), 'rowOnly')
        ->assertSet('tableState.modal.halt.show', false)
        ->assertSet('log', []);
});

it('round-trips a key through the encoding', function () {
    expect(SubRowKey::decode(SubRowKey::encode('01J9ABC', 42)))->toBe(['01J9ABC', '42'])
        ->and(SubRowKey::decode('17'))->toBeNull()
        ->and(SubRowKey::decode(17))->toBeNull()
        ->and(SubRowKey::decode('~sub~5'))->toBeNull();
});
