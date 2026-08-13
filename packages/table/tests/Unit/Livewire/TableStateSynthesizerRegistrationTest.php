<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Core\State\StateContainer;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Livewire\TableStateSynthesizer;
use NyonCode\WireTable\Table;

/*
 * The provider's registration of TableStateSynthesizer, pinned from the outside.
 *
 * `$tableState` is a StateContainer, a type Livewire cannot dehydrate on its own —
 * without a registered synthesizer it throws "Property type not supported in
 * Livewire for property". So a round trip is the proof, and the synth key in the
 * snapshot is the proof that OUR synth did it rather than something else.
 *
 * Worth its own test because the registration seam is version-sensitive: Livewire 4
 * moved the registry from `HandleComponents::registerPropertySynthesizer()` (gone)
 * to `HandleSynths::registerSynth()`, and the provider now goes through
 * `Livewire::propertySynthesizer()`, which forwards to the right owner in both. A
 * regression here is silent in every test that only reads rendered markup.
 */

class TssrRow extends Model
{
    protected $table = 'tssr_rows';

    protected $guarded = [];
}

class TssrHost extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table->model(TssrRow::class)->searchable()->columns([
            Column::make('title')->searchable(),
        ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    Schema::create('tssr_rows', function (Blueprint $t) {
        $t->id();
        $t->string('title');
        $t->timestamps();
    });

    TssrRow::insert([
        ['title' => 'Alpha'],
        ['title' => 'Beta'],
    ]);
});

afterEach(fn () => Schema::dropIfExists('tssr_rows'));

it('dehydrates tableState through the registered synthesizer', function () {
    $snapshot = Livewire::test(TssrHost::class)->snapshot;

    // A synthesised property is a [data, meta] tuple whose meta carries the key.
    expect($snapshot['data']['tableState'][1]['s'])->toBe(TableStateSynthesizer::$key);
});

it('round-trips a dot-notation write into the state container', function () {
    Livewire::test(TssrHost::class)
        ->set('tableState.search', 'Alpha')
        ->assertSet('tableState.search', 'Alpha')
        ->assertSee('Alpha')
        ->assertDontSee('Beta');
});

it('rehydrates the container as a StateContainer, not an array', function () {
    Livewire::test(TssrHost::class)
        ->set('tableState.search', 'Beta')
        ->tap(fn ($t) => expect($t->instance()->tableState)->toBeInstanceOf(StateContainer::class));
});
