<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * In-memory stand-in for a persisted option table for the edit-option workflow.
 */
class EditOptionStore
{
    /** @var array<string, string> */
    public static array $records = ['c1' => 'News', 'c2' => 'Sport'];

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::$records;
    }

    public static function label(string|int $key): ?string
    {
        return self::$records[$key] ?? null;
    }

    public static function update(string|int $key, string $name): void
    {
        self::$records[$key] = $name;
    }

    public static function reset(): void
    {
        self::$records = ['c1' => 'News', 'c2' => 'Sport'];
    }
}

class EditOptionSelectComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['category' => 'c1', 'tags' => ['c1'], 'plain' => null, 'brokenEdit' => 'c1'];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->options(fn () => EditOptionStore::all())
                    ->getOptionLabelUsing(fn ($value) => EditOptionStore::label($value))
                    ->editOptionForm([TextInput::make('name')->required()])
                    ->fillEditOptionUsing(fn ($value) => ['name' => EditOptionStore::label($value)])
                    ->updateOptionUsing(fn ($value, array $data) => EditOptionStore::update($value, (string) $data['name'])),
                Select::make('tags')
                    ->multiple()
                    ->options(fn () => EditOptionStore::all())
                    ->editOptionForm([TextInput::make('name')]),
                Select::make('plain')->options(['a' => 'A']),
                Select::make('brokenEdit')
                    ->options(fn () => EditOptionStore::all())
                    ->editOptionForm(fn () => 'not-an-array')
                    ->updateOptionUsing(fn () => null),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(fn () => EditOptionStore::reset());

test('mountEditOption opens the modal and fills the form from the selected record', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.category')
        ->assertSet('mountedEditOptionSelect', 'data.category')
        ->assertSet('editOptionFormData.name', 'News');
});

test('mountEditOption is a no-op for a select without an edit form', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.plain')
        ->assertSet('mountedEditOptionSelect', null);
});

test('mountEditOption is a no-op for a multiple select', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.tags')
        ->assertSet('mountedEditOptionSelect', null);
});

test('mountEditOption is a no-op when nothing is selected', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->set('data.category', null)
        ->call('mountEditOption', 'data.category')
        ->assertSet('mountedEditOptionSelect', null);
});

test('mountEditOption is a no-op for an unknown state path', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.missing')
        ->assertSet('mountedEditOptionSelect', null);
});

test('updateSelectOption validates the modal form and keeps it open on error', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.category')
        ->set('editOptionFormData.name', '')
        ->call('updateSelectOption')
        ->assertHasErrors('editOptionFormData.name')
        ->assertSet('mountedEditOptionSelect', 'data.category');
});

test('updateSelectOption persists the edit and closes the modal', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.category')
        ->set('editOptionFormData.name', 'Headlines')
        ->call('updateSelectOption')
        ->assertHasNoErrors()
        ->assertSet('mountedEditOptionSelect', null)
        ->assertSet('editOptionFormData', []);

    expect(EditOptionStore::label('c1'))->toBe('Headlines');
});

test('updateSelectOption is a no-op when no select is mounted', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('updateSelectOption')
        ->assertSet('mountedEditOptionSelect', null);
});

test('updateSelectOption bails out when the mounted field is no longer edit-enabled', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->set('mountedEditOptionSelect', 'data.plain')
        ->call('updateSelectOption')
        ->assertSet('mountedEditOptionSelect', null);
});

test('updateSelectOption bails out when the edit form cannot be built', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.brokenEdit')
        ->assertSet('mountedEditOptionSelect', 'data.brokenEdit')
        ->call('updateSelectOption')
        ->assertSet('mountedEditOptionSelect', null);
});

test('updateSelectOption bails out when the selection was cleared after mounting', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.category')
        ->set('data.category', null)
        ->call('updateSelectOption')
        ->assertSet('mountedEditOptionSelect', null);

    expect(EditOptionStore::label('c1'))->toBe('News');
});

test('unmountEditOption closes the modal and clears its data', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->call('mountEditOption', 'data.category')
        ->call('unmountEditOption')
        ->assertSet('mountedEditOptionSelect', null)
        ->assertSet('editOptionFormData', []);
});

test('the edit-enabled select renders the edit button and modal wiring', function () {
    Livewire::test(EditOptionSelectComponent::class)
        ->assertSeeHtml("mountEditOption('data.category')")
        ->assertSee('Edit option')
        ->assertDontSeeHtml('wire:click="updateSelectOption"')
        ->call('mountEditOption', 'data.category')
        ->assertSeeHtml('wire:click="updateSelectOption"');
});
