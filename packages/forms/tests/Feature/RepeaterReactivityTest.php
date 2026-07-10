<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * Reactive dispatch must reach fields inside repeater items (regression:
 * flattening treats a repeater as a leaf, so afterStateUpdated, live
 * validation, field actions and remote search silently no-oped for item
 * fields — audit matrix A, column RI).
 */
class RepeaterReactivityComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [
        'contacts' => [
            ['name' => 'Ada', 'slug' => '', 'email' => 'ada@example.com', 'category' => null, 'type' => 'other', 'other' => ''],
            ['name' => 'Grace', 'slug' => '', 'email' => 'grace@example.com', 'category' => null, 'type' => 'email', 'other' => ''],
        ],
    ];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Repeater::make('contacts')->schema([
                    TextInput::make('name')
                        ->live()
                        ->afterStateUpdated(fn ($set, $state) => $set('slug', strtolower((string) $state))),
                    TextInput::make('slug'),
                    TextInput::make('email')
                        ->rules(['required', 'email'])
                        ->validateLive()
                        ->suffixAction(Action::make('clear')->action(fn ($set) => $set('email', ''))),
                    Select::make('category')
                        ->getSearchResultsUsing(fn (string $search) => ['hit' => 'Found: '.$search]),
                    Select::make('type')
                        ->options(['email' => 'Email', 'other' => 'Other'])
                        ->live(),
                    TextInput::make('other')->visibleWhen('type', 'other'),
                ]),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class RepeaterItemLabelComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = [
        'contacts' => [
            ['name' => 'Ada'],
            ['name' => 'Grace'],
        ],
    ];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Repeater::make('contacts')
                    ->schema([TextInput::make('name')])
                    ->itemLabel(fn (array $state) => 'Contact: '.($state['name'] ?? '?')),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('repeater renders a per-item label next to each item number', function () {
    Livewire::test(RepeaterItemLabelComponent::class)
        ->assertSeeHtml('#1')
        ->assertSeeHtml('#2')
        ->assertSee('Contact: Ada')      // label derived from item 0's state
        ->assertSee('Contact: Grace');   // label derived from item 1's state
});

test('afterStateUpdated fires for a field inside a repeater item and $set writes into that item', function () {
    Livewire::test(RepeaterReactivityComponent::class)
        ->set('data.contacts.1.name', 'LOVELACE')
        ->assertSet('data.contacts.1.slug', 'lovelace')
        // The sibling item is untouched.
        ->assertSet('data.contacts.0.slug', '');
});

test('live validation reports errors for a field inside a repeater item', function () {
    Livewire::test(RepeaterReactivityComponent::class)
        ->set('data.contacts.0.email', 'not-an-email')
        ->assertHasErrors(['data.contacts.0.email'])
        ->set('data.contacts.0.email', 'ada@example.org')
        ->assertHasNoErrors(['data.contacts.0.email']);
});

test('a field action dispatches for a field inside a repeater item', function () {
    Livewire::test(RepeaterReactivityComponent::class)
        ->call('callFieldAction', 'data.contacts.1.email', 'clear')
        ->assertSet('data.contacts.1.email', '')
        ->assertSet('data.contacts.0.email', 'ada@example.com');
});

test('remote select search resolves for a field inside a repeater item', function () {
    Livewire::test(RepeaterReactivityComponent::class)
        ->call('searchSelectOptions', 'data.contacts.0.category', 'abc')
        ->assertReturned(['hit' => 'Found: abc']);
});

test('visibleWhen evaluates per item (regression: repeater rendered every item field unconditionally)', function () {
    Livewire::test(RepeaterReactivityComponent::class)
        ->assertSeeHtml('data.contacts.0.other')
        ->assertDontSeeHtml('data.contacts.1.other')
        // Flipping the controlling field re-evaluates visibility on the roundtrip.
        ->set('data.contacts.1.type', 'other')
        ->assertSeeHtml('data.contacts.1.other');
});
