<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Button;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

class FieldActionsComponent extends Component
{
    /** @var array<string, mixed> */
    public array $data = ['title' => 'Hello World', 'slug' => ''];

    use WithForms;

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('title')->suffixAction(
                    Action::make('to_upper')
                        ->action(fn ($get, $set) => $set('title', strtoupper((string) $get('title')))),
                ),
                TextInput::make('slug'),
                Button::make('generate_slug')
                    ->label('Generate slug')
                    ->action(fn ($get, $set) => $set('slug', Str::slug((string) $get('title')))),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

/**
 * Mirrors a table-style host that exposes its action-modal form via
 * getActionModalFormInstance() (instead of WithForms' getForms()), exercising
 * the modal-form branch of the field-action resolver.
 */
class ModalFormHostComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $modalData = ['title' => 'hello'];

    public function getActionModalFormInstance(): Form
    {
        $form = app(Form::class);
        $form->livewire($this);

        return $form->statePath('modalData')->schema([
            TextInput::make('title')->suffixAction(
                Action::make('to_upper')
                    ->action(fn ($get, $set) => $set('title', strtoupper((string) $get('title')))),
            ),
        ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class NoopButtonComponent extends Component
{
    /** @var array<string, mixed> */
    public array $data = [];

    use WithForms;

    public function form(Form $form): Form
    {
        return $form->statePath('data')->schema([
            Button::make('press'),
        ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('a suffix action runs its callback with the field $get/$set context', function () {
    Livewire::test(FieldActionsComponent::class)
        ->call('callFieldAction', 'data.title', 'to_upper')
        ->assertSet('data.title', 'HELLO WORLD');
});

test('a Button field runs its callback with the form $get/$set context', function () {
    Livewire::test(FieldActionsComponent::class)
        ->call('callFieldAction', 'data.generate_slug', 'generate_slug')
        ->assertSet('data.slug', 'hello-world');
});

test('the rendered form includes the affix action and button markup', function () {
    Livewire::test(FieldActionsComponent::class)
        ->assertSeeHtml("callFieldAction('data.title', 'to_upper')")
        ->assertSeeHtml("callFieldAction('data.generate_slug', 'generate_slug')")
        ->assertSee('Generate slug');
});

test('an unknown state path is a no-op', function () {
    Livewire::test(FieldActionsComponent::class)
        ->call('callFieldAction', 'data.missing', 'to_upper')
        ->assertSet('data.title', 'Hello World');
});

test('an unknown action name on a real field is a no-op', function () {
    Livewire::test(FieldActionsComponent::class)
        ->call('callFieldAction', 'data.title', 'nope')
        ->assertSet('data.title', 'Hello World');
});

test('a button with no callback is a no-op', function () {
    Livewire::test(NoopButtonComponent::class)
        ->call('callFieldAction', 'data.press', 'press')
        ->assertOk();
});

test('field actions resolve through a host action-modal form instance', function () {
    Livewire::test(ModalFormHostComponent::class)
        ->call('callFieldAction', 'modalData.title', 'to_upper')
        ->assertSet('modalData.title', 'HELLO');
});
