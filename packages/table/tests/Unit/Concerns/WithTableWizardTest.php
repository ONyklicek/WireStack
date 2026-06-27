<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\HeaderAction;
use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\Concerns\WithTable;
use NyonCode\WireTable\Table;

// ─── Test model & component ──────────────────────────────────────

class WtwUser extends Model
{
    protected $table = 'wtw_users';

    protected $guarded = [];

    public $timestamps = false;
}

class WtwComponent extends Component
{
    use WithTable;

    public static bool $executed = false;

    public static bool $afterRan = false;

    public function table(Table $table): Table
    {
        return $table
            ->model(WtwUser::class)
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
            ])
            ->headerActions([
                HeaderAction::make('wizard')
                    ->steps([
                        ModalStep::make('Account')
                            ->schema([TextInput::make('name')])
                            ->validation(['name' => 'required|min:2'])
                            ->afterValidation(function () {
                                WtwComponent::$afterRan = true;
                            }),
                        ModalStep::make('Contact')
                            ->schema([TextInput::make('email')])
                            ->validation(['email' => 'required|email'])
                            ->before(fn () => ['email' => 'pre@filled.com']),
                    ])
                    ->action(function () {
                        WtwComponent::$executed = true;
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

    Schema::create('wtw_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    WtwComponent::$executed = false;
    WtwComponent::$afterRan = false;
});

afterEach(function () {
    Schema::dropIfExists('wtw_users');
});

// ─── Opening ─────────────────────────────────────────────────────

it('opens a wizard action modal at the first step', function () {
    $test = Livewire::test(WtwComponent::class)->call('openHeaderActionModal', 'wizard');
    $component = $test->instance();

    expect($component->tableState->get('modal.action.show'))->toBeTrue()
        ->and($component->tableState->get('modal.action.currentStep'))->toBe(0)
        ->and($component->getActionModalFormInstance())->toBeInstanceOf(Form::class);
});

// ─── Forward navigation ──────────────────────────────────────────

it('advances to the next step when the current step is valid', function () {
    $test = Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'Jane'])
        ->call('nextActionModalStep')
        ->assertHasNoErrors();

    expect($test->instance()->tableState->get('modal.action.currentStep'))->toBe(1);
});

it('blocks advancing when the current step fails validation', function () {
    $test = Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'J'])
        ->call('nextActionModalStep')
        ->assertHasErrors('name');

    expect($test->instance()->tableState->get('modal.action.currentStep'))->toBe(0);
});

it('does not advance past the last step', function () {
    $test = Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'Jane'])
        ->call('nextActionModalStep')
        ->set('tableState.modal.action.formData', ['name' => 'Jane', 'email' => 'jane@example.com'])
        ->call('nextActionModalStep')
        ->assertHasNoErrors();

    expect($test->instance()->tableState->get('modal.action.currentStep'))->toBe(1);
});

// ─── Hooks ───────────────────────────────────────────────────────

it('runs afterValidation on advance and prefills the next step via before', function () {
    $test = Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'Jane'])
        ->call('nextActionModalStep');

    $formData = $test->instance()->tableState->get('modal.action.formData');

    expect(WtwComponent::$afterRan)->toBeTrue()
        ->and($formData['email'] ?? null)->toBe('pre@filled.com');
});

// ─── Backward navigation ─────────────────────────────────────────

it('steps back without validating', function () {
    $test = Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'Jane'])
        ->call('nextActionModalStep')
        ->set('tableState.modal.action.formData', ['name' => '']) // invalid, but back skips validation
        ->call('prevActionModalStep')
        ->assertHasNoErrors();

    expect($test->instance()->tableState->get('modal.action.currentStep'))->toBe(0);
});

it('does not step back before the first step', function () {
    $test = Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->call('prevActionModalStep');

    expect($test->instance()->tableState->get('modal.action.currentStep'))->toBe(0);
});

// ─── Submit ──────────────────────────────────────────────────────

it('validates every step cumulatively on submit', function () {
    Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'Jane', 'email' => 'not-an-email'])
        ->call('submitActionModal')
        ->assertHasErrors('email');

    expect(WtwComponent::$executed)->toBeFalse();
});

it('executes the action when all steps are valid', function () {
    Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->set('tableState.modal.action.formData', ['name' => 'Jane', 'email' => 'jane@example.com'])
        ->call('submitActionModal')
        ->assertHasNoErrors();

    expect(WtwComponent::$executed)->toBeTrue();
});

// ─── Rendering ───────────────────────────────────────────────────

it('renders the step indicator and Back/Next controls', function () {
    Livewire::test(WtwComponent::class)
        ->call('openHeaderActionModal', 'wizard')
        ->assertSeeHtml('wire:click="nextActionModalStep"')
        ->set('tableState.modal.action.formData', ['name' => 'Jane'])
        ->call('nextActionModalStep')
        ->assertSeeHtml('wire:click="prevActionModalStep"')
        ->assertSeeHtml('wire:click="submitActionModal"');
});
