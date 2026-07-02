<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Server-driven per-step validation of the standalone Wizard layout: the "Next"
 * button calls validateWizardStep() and only advances when the current step's
 * fields pass — later steps stay unflagged, fixed failures are cleared, and a
 * failed full-form submit reports which step to jump to.
 */
class WizardStepValidationComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [
        'name' => '',
        'email' => '',
        'contacts' => [
            ['label' => ''],
        ],
        'city' => '',
        'wantsExtras' => false,
        'extra' => '',
    ];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Wizard::make('signup')->schema([
                    Step::make('account')->schema([
                        TextInput::make('name')->rules(['required']),
                        TextInput::make('email')->rules(['required', 'email']),
                        Repeater::make('contacts')->schema([
                            TextInput::make('label')->rules(['required']),
                        ]),
                    ]),
                    Step::make('address')->schema([
                        TextInput::make('city')->rules(['required']),
                    ]),
                    Step::make('extras')
                        ->visible(fn ($get) => (bool) $get('wantsExtras'))
                        ->schema([
                            TextInput::make('extra')->rules(['required']),
                        ]),
                ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('an invalid step reports errors for its own fields only and does not advance', function () {
    Livewire::test(WizardStepValidationComponent::class)
        ->call('validateWizardStep', 0, 'signup')
        ->assertReturned(false)
        ->assertHasErrors(['data.name', 'data.email', 'data.contacts.0.label'])
        // The later step's field is untouched by step-0 validation.
        ->assertHasNoErrors(['data.city']);
});

test('a valid step passes and clears its previously reported errors', function () {
    Livewire::test(WizardStepValidationComponent::class)
        ->call('validateWizardStep', 0, 'signup')
        ->assertHasErrors(['data.name', 'data.contacts.0.label'])
        ->set('data.name', 'Ada')
        ->set('data.email', 'ada@example.com')
        ->set('data.contacts.0.label', 'Home')
        ->call('validateWizardStep', 0, 'signup')
        ->assertReturned(true)
        ->assertHasNoErrors(['data.name', 'data.email', 'data.contacts.0.label']);
});

test('the second step validates independently of the first', function () {
    Livewire::test(WizardStepValidationComponent::class)
        ->call('validateWizardStep', 1, 'signup')
        ->assertReturned(false)
        ->assertHasErrors(['data.city'])
        ->assertHasNoErrors(['data.name']);
});

test('the wizard resolves without a name (first wizard in the schema)', function () {
    Livewire::test(WizardStepValidationComponent::class)
        ->call('validateWizardStep', 1)
        ->assertReturned(false)
        ->assertHasErrors(['data.city']);
});

test('an unknown wizard name or out-of-range step gates nothing', function () {
    Livewire::test(WizardStepValidationComponent::class)
        ->call('validateWizardStep', 0, 'nonexistent')
        ->assertReturned(true)
        ->call('validateWizardStep', 99, 'signup')
        ->assertReturned(true);
});

test('step indices follow visible steps — a hidden step does not shift validation', function () {
    // With wantsExtras off, the "extras" step is invisible: index 1 is "address"
    // and index 2 does not exist.
    Livewire::test(WizardStepValidationComponent::class)
        ->call('validateWizardStep', 2, 'signup')
        ->assertReturned(true)
        // Once the toggle reveals the step, index 2 validates its field.
        ->set('data.wantsExtras', true)
        ->call('validateWizardStep', 2, 'signup')
        ->assertReturned(false)
        ->assertHasErrors(['data.extra']);
});

test('the rendered wizard wires the server-validated Next button', function () {
    Livewire::test(WizardStepValidationComponent::class)
        ->assertSeeHtml('validateWizardStep')
        ->assertSeeHtml("wizard: 'signup'");
});

test('a failed full-form validation marks the first errored step for the client jump', function () {
    $component = Livewire::test(WizardStepValidationComponent::class)
        ->set('data.name', 'Ada')
        ->set('data.email', 'ada@example.com')
        ->set('data.contacts.0.label', 'Home')
        ->call('validateWizardStep', 1, 'signup');

    // Only the "address" step (index 1) has errors → the sync call carries 1.
    expect($component->html())->toContain('sync(2, 1)');
});

test('with no errors the sync call carries a null error step', function () {
    $component = Livewire::test(WizardStepValidationComponent::class);

    expect($component->html())->toContain('sync(2, null)');
});
