<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

/**
 * In-memory stand-in for a persisted option table, so the create-option workflow
 * can be exercised without a database.
 */
class OptionStore
{
    /** @var array<string, string> */
    public static array $records = ['c1' => 'News'];

    public static int $sequence = 1;

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::$records;
    }

    public static function label(string|int $key): ?string
    {
        return self::$records[$key] ?? null;
    }

    public static function create(string $name): string
    {
        self::$sequence++;
        $key = 'c'.self::$sequence;
        self::$records[$key] = $name;

        return $key;
    }

    public static function reset(): void
    {
        self::$records = ['c1' => 'News'];
        self::$sequence = 1;
    }
}

class CreateOptionSelectComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['category' => null, 'tags' => []];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->options(fn () => OptionStore::all())
                    ->getOptionLabelUsing(fn ($value) => OptionStore::label($value))
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->createOptionUsing(fn (array $data) => OptionStore::create((string) $data['name'])),
                Select::make('tags')
                    ->multiple()
                    ->options(fn () => OptionStore::all())
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->createOptionUsing(fn (array $data) => OptionStore::create((string) $data['name'])),
                Select::make('plain')->options(['a' => 'A']),
                Select::make('broken')
                    ->createOptionForm(fn () => 'not-an-array')
                    ->createOptionUsing(fn () => 'x'),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

beforeEach(fn () => OptionStore::reset());

test('mountCreateOption opens the modal for a create-enabled select', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->assertSet('mountedCreateOptionSelect', 'data.category');
});

test('mountCreateOption is a no-op for a select without a create form', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.plain')
        ->assertSet('mountedCreateOptionSelect', null);
});

test('mountCreateOption is a no-op for an unknown state path', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.missing')
        ->assertSet('mountedCreateOptionSelect', null);
});

test('createSelectOption validates the modal form and keeps it open on error', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->call('createSelectOption')
        ->assertHasErrors('createOptionFormData.name')
        ->assertSet('mountedCreateOptionSelect', 'data.category')
        ->assertSet('data.category', null);
});

test('createSelectOption persists the option, selects it, and closes the modal', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', 'Sport')
        ->call('createSelectOption')
        ->assertHasNoErrors()
        ->assertSet('data.category', 'c2')
        ->assertSet('mountedCreateOptionSelect', null)
        ->assertSet('createOptionFormData', []);

    expect(OptionStore::label('c2'))->toBe('Sport');
});

test('createSelectOption appends the new value for a multiple select', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->set('data.tags', ['c1'])
        ->call('mountCreateOption', 'data.tags')
        ->set('createOptionFormData.name', 'Culture')
        ->call('createSelectOption')
        ->assertSet('data.tags', ['c1', 'c2']);
});

test('createSelectOption is a no-op when no select is mounted', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('createSelectOption')
        ->assertSet('data.category', null)
        ->assertSet('mountedCreateOptionSelect', null);
});

test('createSelectOption bails out when the mounted field is no longer create-enabled', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->set('mountedCreateOptionSelect', 'data.plain')
        ->call('createSelectOption')
        ->assertSet('mountedCreateOptionSelect', null)
        ->assertSet('data.plain', null);
});

test('createSelectOption bails out when the create form cannot be built', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.broken')
        ->assertSet('mountedCreateOptionSelect', 'data.broken')
        ->call('createSelectOption')
        ->assertSet('mountedCreateOptionSelect', null)
        ->assertSet('data.broken', null);
});

test('unmountCreateOption closes the modal and clears its data', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', 'Draft')
        ->call('unmountCreateOption')
        ->assertSet('mountedCreateOptionSelect', null)
        ->assertSet('createOptionFormData', []);
});

test('the create-enabled select renders the create button in its panel', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->assertSeeHtml("mountCreateOption('data.category')")
        ->assertSee('Create option');
});

test('the create modal renders only once the select is mounted', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->assertDontSeeHtml('wire:click="createSelectOption"')
        ->call('mountCreateOption', 'data.category')
        ->assertSeeHtml('wire:click="createSelectOption"');
});
