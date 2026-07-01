<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * @var array<string, string>
 */
const REMOTE_SELECT_DATASET = [
    'u1' => 'John Doe',
    'u2' => 'Jane Roe',
    'u3' => 'Johnny Cash',
];

class RemoteSelectComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['user' => 'u1'];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('user')
                    ->getSearchResultsUsing(fn (string $search) => collect(REMOTE_SELECT_DATASET)
                        ->filter(fn (string $label) => $search === '' || str_contains(strtolower($label), strtolower($search)))
                        ->all())
                    ->getOptionLabelUsing(fn ($value) => REMOTE_SELECT_DATASET[$value] ?? null),
                Select::make('role')->options(['admin' => 'Admin'])->searchable(),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('searchSelectOptions returns the field callback results filtered by term', function () {
    Livewire::test(RemoteSelectComponent::class)
        ->call('searchSelectOptions', 'data.user', 'jane')
        ->assertReturned(['u2' => 'Jane Roe']);
});

test('searchSelectOptions matches multiple records case-insensitively', function () {
    Livewire::test(RemoteSelectComponent::class)
        ->call('searchSelectOptions', 'data.user', 'john')
        ->assertReturned(['u1' => 'John Doe', 'u3' => 'Johnny Cash']);
});

test('searchSelectOptions on an unknown state path returns an empty array', function () {
    Livewire::test(RemoteSelectComponent::class)
        ->call('searchSelectOptions', 'data.missing', 'john')
        ->assertReturned([]);
});

test('searchSelectOptions on a select without a remote callback returns an empty array', function () {
    Livewire::test(RemoteSelectComponent::class)
        ->call('searchSelectOptions', 'data.role', 'admin')
        ->assertReturned([]);
});

test('the remote select seeds the current selection label into the trigger', function () {
    Livewire::test(RemoteSelectComponent::class)
        ->assertSee('John Doe')
        ->assertSeeHtml("searchSelectOptions('data.user'");
});
