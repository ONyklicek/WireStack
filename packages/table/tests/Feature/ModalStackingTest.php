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

class MsUser extends Model
{
    protected $table = 'ms_users';

    protected $guarded = [];

    public $timestamps = false;
}

class MsStackComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(MsUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->actions([
                Action::make('edit')
                    ->modalHeading('Edit user')
                    ->form([TextInput::make('name')])
                    ->action(fn () => null),
                Action::make('note')
                    ->modalHeading('Add note')
                    ->form([TextInput::make('note')])
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

    Schema::create('ms_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    MsUser::create(['name' => 'Ada']);
});

afterEach(function () {
    Schema::dropIfExists('ms_users');
});

it('stacks a second table action modal on top of the first', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note');

    $state = $test->instance()->tableState;

    expect($state->get('modal.action.name'))->toBe('note')
        ->and($state->get('modal.action.show'))->toBeTrue()
        ->and($state->get('modal.suspended'))->toHaveCount(1)
        ->and($state->get('modal.suspended')[0]['name'])->toBe('edit');
});

it('resumes the parent table modal when the stacked one closes', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note')
        ->call('closeActionModal');

    $state = $test->instance()->tableState;

    expect($state->get('modal.action.name'))->toBe('edit')
        ->and($state->get('modal.action.show'))->toBeTrue()
        ->and($state->get('modal.suspended'))->toBeEmpty();

    // Closing the last (parent) modal clears it fully.
    $test->call('closeActionModal');
    expect($test->instance()->tableState->get('modal.action.show'))->toBeFalse();
});

it('preserves the parent table modal form data across a stacked modal', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->set('tableState.modal.action.formData.name', 'Grace')
        ->call('openActionModal', '1', 'note')
        ->call('closeActionModal');

    expect($test->instance()->tableState->get('modal.action.formData.name'))->toBe('Grace');
});

it('exposes suspended table modals for rendering', function () {
    $test = Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note');

    $suspended = $test->instance()->getSuspendedActionModals();

    expect($suspended)->toHaveCount(1)
        ->and($suspended[0]['heading'])->toBe('Edit user');
});

it('renders the suspended parent shell and elevates the active modal z-index', function () {
    Livewire::test(MsStackComponent::class)
        ->call('openActionModal', '1', 'edit')
        ->call('openActionModal', '1', 'note')
        ->assertSeeHtml('Edit user')
        ->assertSeeHtml('Add note')
        ->assertSeeHtml('z-index: 60');
});
