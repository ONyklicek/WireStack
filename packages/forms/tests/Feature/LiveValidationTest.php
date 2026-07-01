<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class LiveValidationComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['type' => 'business', 'name' => 'seed', 'company' => '', 'note' => ''];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->validationMessages(['required' => 'Name is mandatory.'])
                    ->validateLive(),
                TextInput::make('company')->requiredIf('type', 'business')->validateLive(),
                TextInput::make('note')->required(), // no live validation
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('a live field reports its own validation error as the user types', function () {
    Livewire::test(LiveValidationComponent::class)
        ->set('data.name', '')
        ->assertHasErrors(['data.name']);
});

test('the live error clears once the field becomes valid', function () {
    Livewire::test(LiveValidationComponent::class)
        ->set('data.name', '')
        ->assertHasErrors(['data.name'])
        ->set('data.name', 'Ada')
        ->assertHasNoErrors(['data.name']);
});

test('live validation respects reactive conditional rules', function () {
    Livewire::test(LiveValidationComponent::class)
        ->set('data.company', '')
        ->assertHasErrors(['data.company'])
        ->set('data.type', 'individual')
        ->set('data.company', '')
        ->assertHasNoErrors(['data.company']);
});

test('changing a live field does not flag other fields', function () {
    Livewire::test(LiveValidationComponent::class)
        ->set('data.name', '')
        ->assertHasErrors(['data.name'])
        ->assertHasNoErrors(['data.note']);
});

test('a non-live field is not validated on change', function () {
    Livewire::test(LiveValidationComponent::class)
        ->set('data.note', '')
        ->assertHasNoErrors(['data.note']);
});

test('live validation surfaces the field custom validation message', function () {
    Livewire::test(LiveValidationComponent::class)
        ->set('data.name', '')
        ->assertHasErrors(['data.name' => 'Name is mandatory.']);
});
