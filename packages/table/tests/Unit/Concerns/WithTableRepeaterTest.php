<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalFooterAction;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

// ─── Test model & component ──────────────────────────────────────

class WtrUser extends Model
{
    protected $table = 'wtr_users';

    protected $guarded = [];

    public $timestamps = false;
}

class WtrComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtrUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('create')
                    ->form(fn () => Form::make()->schema([
                        Repeater::make('contacts')->schema([
                            TextInput::make('label'),
                        ]),
                    ]))
                    ->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtrReactiveComponent extends Component
{
    use WithTable;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtrUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('create')
                    ->form(fn () => Form::make()->schema([
                        TextInput::make('type')->afterStateUpdated(
                            fn ($state, $set) => $set('vat_id', $state === 'business' ? 'AUTO' : null),
                        ),
                        TextInput::make('vat_id'),
                    ]))
                    ->action(fn () => null),
            ]);
    }

    public function render()
    {
        return $this->getTableProperty();
    }
}

class WtrFooterActionComponent extends Component
{
    use WithTable;

    /** @var array<int, mixed> */
    public array $footerLog = [];

    public function table(Table $table): Table
    {
        return $table
            ->model(WtrUser::class)
            ->paginated(false)
            ->columns([TextColumn::make('name')])
            ->headerActions([
                HeaderAction::make('create')
                    ->form(fn () => Form::make()->schema([
                        TextInput::make('name')->required(),
                    ]))
                    ->modalFooterActions([
                        ModalFooterAction::make('echo')
                            ->action(fn ($data, $component) => $component->footerLog[] = $data['name'] ?? null),
                        ModalFooterAction::make('seed')
                            ->action(fn ($set) => $set('name', 'SEEDED')),
                        ModalFooterAction::make('done')
                            ->closesModal()
                            ->action(fn () => null),
                        ModalFooterAction::make('validate')
                            ->submitsForm()
                            ->action(fn ($component) => $component->footerLog[] = 'ran'),
                    ])
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

    Schema::create('wtr_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    Schema::dropIfExists('wtr_users');
});

// ─── Repeater actions are available on the table host (regression) ─────────

it('exposes the repeater item actions on a WithTable component', function () {
    $repeaterMethods = ['addRepeaterItem', 'removeRepeaterItem', 'reorderRepeaterItems'];

    foreach ($repeaterMethods as $method) {
        expect(method_exists(WtrComponent::class, $method))->toBeTrue();
    }
});

it('adds a repeater item into the table modal form-data bag', function () {
    $path = 'tableState.modal.action.formData.contacts';

    $test = Livewire::test(WtrComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('addRepeaterItem', $path)
        ->call('addRepeaterItem', $path);

    $contacts = $test->instance()->tableState->get('modal.action.formData.contacts');

    expect($contacts)->toBeArray()->toHaveCount(2);
});

it('removes a repeater item by index from the table modal bag', function () {
    $path = 'tableState.modal.action.formData.contacts';

    $test = Livewire::test(WtrComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData.contacts', [
            ['label' => 'a'],
            ['label' => 'b'],
        ])
        ->call('removeRepeaterItem', $path, 0);

    $contacts = $test->instance()->tableState->get('modal.action.formData.contacts');

    expect($contacts)->toHaveCount(1)
        ->and($contacts[0]['label'])->toBe('b');
});

it('reorders repeater items in the table modal bag', function () {
    $path = 'tableState.modal.action.formData.contacts';

    $test = Livewire::test(WtrComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData.contacts', [
            ['label' => 'a'],
            ['label' => 'b'],
        ])
        ->call('reorderRepeaterItems', $path, [1, 0]);

    $contacts = $test->instance()->tableState->get('modal.action.formData.contacts');

    expect($contacts[0]['label'])->toBe('b')
        ->and($contacts[1]['label'])->toBe('a');
});

// ─── afterStateUpdated() fires inside a table action modal (regression) ──────

it('runs afterStateUpdated for a field in a header action modal form', function () {
    $test = Livewire::test(WtrReactiveComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData.type', 'business');

    expect($test->instance()->tableState->get('modal.action.formData.vat_id'))->toBe('AUTO');
});

it('afterStateUpdated clears the dependent field when the trigger is not business', function () {
    $test = Livewire::test(WtrReactiveComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData.vat_id', 'preset')
        ->set('tableState.modal.action.formData.type', 'individual');

    expect($test->instance()->tableState->get('modal.action.formData.vat_id'))->toBeNull();
});

// ─── Modal footer actions (Action::modalFooterActions()) ─────────────────────

it('runs a footer action callback with the live form-data bag', function () {
    $test = Livewire::test(WtrFooterActionComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->set('tableState.modal.action.formData.name', 'Bob')
        ->call('callModalFooterAction', 'echo');

    expect($test->instance()->footerLog)->toBe(['Bob']);
});

it('a footer action can $set values back into the form-data bag', function () {
    $test = Livewire::test(WtrFooterActionComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('callModalFooterAction', 'seed');

    expect($test->instance()->tableState->get('modal.action.formData.name'))->toBe('SEEDED');
});

it('a footer action with closesModal() closes the modal', function () {
    $test = Livewire::test(WtrFooterActionComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('callModalFooterAction', 'done');

    expect($test->instance()->tableState->get('modal.action.show'))->toBeFalse();
});

it('a submitsForm() footer action validates the form first', function () {
    // name is required and empty → validation must block the callback.
    $test = Livewire::test(WtrFooterActionComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('callModalFooterAction', 'validate');

    $test->assertHasErrors('tableState.modal.action.formData.name');
    expect($test->instance()->footerLog)->toBe([]);
});

it('ignores an unknown footer action name', function () {
    $test = Livewire::test(WtrFooterActionComponent::class)
        ->call('openHeaderActionModal', 'create')
        ->call('callModalFooterAction', 'nope');

    expect($test->instance()->footerLog)->toBe([])
        ->and($test->instance()->tableState->get('modal.action.show'))->toBeTrue();
});
