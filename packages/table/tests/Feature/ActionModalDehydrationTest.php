<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

/*
 * A table's action modal submits through the same write-path seam as
 * Form::save() (ADR 0021) — see the sibling test in wire-forms for the
 * mechanism. This is the table host's half: the seam is composed into WithTable
 * through InteractsWithActionForms, and submitActionModal() is the call site.
 */

class AmdOffer extends Model
{
    protected $table = 'amd_offers';

    protected $guarded = [];

    public $timestamps = false;
}

class AmdComponent extends Component
{
    use WithTable;

    /** @var array<string, mixed>|null */
    public ?array $submitted = null;

    public function table(Table $table): Table
    {
        return $table
            ->model(AmdOffer::class)
            ->paginated(false)
            ->columns([TextColumn::make('title')])
            ->headerActions([
                HeaderAction::make('create')
                    ->form([TextInput::make('discount')->numeric(), TextInput::make('title')])
                    ->action(fn (array $data) => $this->submitted = $data),
            ])
            ->actions([
                Action::make('edit')
                    ->form([TextInput::make('discount')->numeric()])
                    ->action(fn (array $data) => $this->submitted = $data),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    Schema::dropIfExists('amd_offers');
    Schema::create('amd_offers', function (Blueprint $table): void {
        $table->id();
        $table->string('title')->nullable();
        $table->decimal('discount', 5, 2)->nullable();
    });
});

it('hands a header action the value the form would have persisted', function () {
    $component = Livewire::test(AmdComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.actions.0.data.discount', '')
        ->set('tableState.modal.actions.0.data.title', '')
        ->call('submitActionModal');

    $bag = $component->instance()->submitted ?? [];

    // A cleared number input is null; '' would die on a decimal column under a
    // strict SQL mode ("Incorrect decimal value: ''").
    expect($bag['discount'])->toBeNull()
        ->and($bag['title'])->toBe('');
});

it('hands a row action the same', function () {
    $offer = AmdOffer::query()->create(['title' => 'One', 'discount' => 10]);

    $component = Livewire::test(AmdComponent::class)
        ->call('openActionModal', (string) $offer->id, 'edit')
        ->set('tableState.modal.actions.0.data.discount', '')
        ->call('submitActionModal');

    expect(($component->instance()->submitted ?? [])['discount'])->toBeNull();
});
