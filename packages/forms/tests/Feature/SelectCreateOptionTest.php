<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireCore\Foundation\Enums\ModalWidth;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Wizard;
use NyonCode\WireCore\Modals\Modal;
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

test('createSelectOption dispatches select-option-created so the combobox updates without a refresh', function () {
    Livewire::test(CreateOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', 'Sport')
        ->call('createSelectOption')
        ->assertDispatched('select-option-created',
            statePath: 'data.category',
            value: 'c2',
            label: 'Sport',
        );
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

class CreateAndEditOptionSelectComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['category' => 'c1'];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->options(fn () => OptionStore::all())
                    ->getOptionLabelUsing(fn ($value) => OptionStore::label($value))
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->createOptionUsing(fn (array $data) => OptionStore::create((string) $data['name']))
                    ->editOptionForm([TextInput::make('name')->required()])
                    ->fillEditOptionUsing(fn ($value) => ['name' => OptionStore::label($value)])
                    ->updateOptionUsing(fn () => null),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class WideOptionModalSelectComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['category' => 'c1'];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->options(fn () => OptionStore::all())
                    ->createOptionForm([TextInput::make('name')->required()])
                    ->createOptionModalWidth('3xl')
                    ->createOptionModal(fn (Modal $modal) => $modal
                        ->description('Name the new category')
                        ->submitLabel('Add it')
                        ->cancelLabel('Never mind')
                        ->closeOnClickAway(false))
                    ->editOptionForm([TextInput::make('name')->required()])
                    ->fillEditOptionUsing(fn ($value) => ['name' => OptionStore::label($value)])
                    ->editOptionModalWidth(ModalWidth::Xl),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('the option modals render at the configured width', function () {
    Livewire::test(WideOptionModalSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->assertSeeHtml('max-w-3xl')
        ->assertDontSeeHtml('max-w-md')
        ->call('unmountCreateOption')
        ->call('mountEditOption', 'data.category')
        ->assertSeeHtml('max-w-xl');
});

test('the option modals fall back to the md width', function () {
    Livewire::test(CreateAndEditOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->assertSeeHtml('max-w-md');
});

test('the modal config reaches the rendered option modal', function () {
    Livewire::test(WideOptionModalSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->assertSee('Name the new category')
        ->assertSee('Add it')
        ->assertSee('Never mind');
});

test('closeOnClickAway(false) drops the backdrop close handler', function () {
    // The escape binding and the header's X button emit the same close
    // expression unconditionally, so the flag is only visible as one fewer
    // occurrence — asserting its plain absence would pass either way.
    $closable = Livewire::test(CreateAndEditOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->html();

    $locked = Livewire::test(WideOptionModalSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->html();

    expect(substr_count($locked, '$wire.unmountCreateOption()'))
        ->toBe(substr_count($closable, '$wire.unmountCreateOption()') - 1);
});

test('the option modal footer keeps its default labels', function () {
    Livewire::test(CreateAndEditOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->assertSee('Create')
        ->assertSee('Cancel')
        ->call('unmountCreateOption')
        ->call('mountEditOption', 'data.category')
        ->assertSee('Save');
});

test('the create and edit option modals keep distinct teleport keys when both are mounted', function () {
    // Nothing makes the two mounted-* properties mutually exclusive, so both
    // option modals can be up at once — and they are the same Modal shell.
    // Livewire morphs by wire:key, so sharing one would let the two contents be
    // swapped into each other.
    $html = Livewire::test(CreateAndEditOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->call('mountEditOption', 'data.category')
        ->html();

    preg_match_all('/wire:key="(wire-modal-[^"]*)"/', $html, $matches);

    expect($matches[1])->toHaveCount(2)
        ->and($matches[1])->toBe(array_unique($matches[1]));
});

// ─── The option form as a first-class host form ──────────────────────────

class OptionFormHostComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['category' => 'c1'];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->options(fn () => OptionStore::all())
                    ->createOptionForm([
                        Wizard::make('opt')->schema([
                            Step::make('Basics')->schema([TextInput::make('name')->required()]),
                            Step::make('Details')->schema([
                                // A remote-search Select nested in the option form: its
                                // search endpoint resolves the field by state path.
                                Select::make('parent')
                                    ->getSearchResultsUsing(fn (string $search) => array_filter(
                                        OptionStore::all(),
                                        fn (string $label) => str_contains(strtolower($label), strtolower($search)),
                                    )),
                            ]),
                        ]),
                    ])
                    ->createOptionUsing(fn (array $data) => OptionStore::create((string) $data['name']))
                    ->editOptionForm([
                        Wizard::make('editopt')->schema([
                            Step::make('Rename')->schema([TextInput::make('name')->required()]),
                            Step::make('Confirm')->schema([TextInput::make('reason')]),
                        ]),
                    ])
                    ->fillEditOptionUsing(fn ($value) => ['name' => OptionStore::label($value)])
                    ->updateOptionUsing(fn () => null),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('a wizard step inside the create-option form refuses to advance while invalid', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', '')
        ->call('validateWizardStep', 0, 'opt')
        ->assertReturned(false)
        ->assertHasErrors('createOptionFormData.name');
});

test('a wizard step inside the create-option form advances once valid', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', 'Sport')
        ->call('validateWizardStep', 0, 'opt')
        ->assertReturned(true)
        ->assertHasNoErrors();
});

test('a fixed step clears the stale errors it left behind', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->call('validateWizardStep', 0, 'opt')
        ->assertHasErrors('createOptionFormData.name')
        ->set('createOptionFormData.name', 'Sport')
        ->call('validateWizardStep', 0, 'opt')
        ->assertHasNoErrors();
});

test('the edit-option form gates its wizard steps too', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('mountEditOption', 'data.category')
        ->set('editOptionFormData.name', '')
        ->call('validateWizardStep', 0, 'editopt')
        ->assertReturned(false)
        ->assertHasErrors('editOptionFormData.name');
});

test('step gating is inert while no option modal is mounted', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('validateWizardStep', 0, 'opt')
        ->assertReturned(true)
        ->assertHasNoErrors();
});

test('a Select nested in the option form reaches the search endpoint', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->call('searchSelectOptions', 'createOptionFormData.parent', 'new')
        ->assertReturned(['c1' => 'News']);
});

test('the nested search endpoint stays closed while the option modal is shut', function () {
    Livewire::test(OptionFormHostComponent::class)
        ->call('searchSelectOptions', 'createOptionFormData.parent', 'new')
        ->assertReturned([]);
});

test('an option modal cannot be opened from inside an option form', function () {
    // One mounted path per kind: honouring this would discard the form the user
    // is filling in.
    Livewire::test(OptionFormHostComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', 'Half typed')
        ->call('mountCreateOption', 'createOptionFormData.parent')
        ->assertSet('mountedCreateOptionSelect', 'data.category')
        ->assertSet('createOptionFormData.name', 'Half typed');
});

// ─── Wizard-aware modal footer ───────────────────────────────────────────

class FooterDrivenWizardComponent extends Component
{
    use WithForms;

    /** @var array<string, mixed> */
    public array $data = ['category' => 'c1'];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('category')
                    ->options(fn () => OptionStore::all())
                    ->createOptionForm([
                        Wizard::make('opt')->navigation(false)->schema([
                            Step::make('Basics')->schema([TextInput::make('name')->required()]),
                            Step::make('Details')->schema([TextInput::make('note')]),
                        ]),
                    ])
                    ->createOptionUsing(fn (array $data) => OptionStore::create((string) $data['name']))
                    ->editOptionForm([
                        Wizard::make('editopt')->navigation(false)->schema([
                            Step::make('Rename')->schema([TextInput::make('name')->required()]),
                            Step::make('Confirm')->schema([TextInput::make('reason')]),
                        ]),
                    ])
                    ->fillEditOptionUsing(fn ($value) => ['name' => OptionStore::label($value)])
                    ->updateOptionUsing(fn () => null),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

test('a wizard that gave up its navigation gets it back in the modal footer', function () {
    $html = Livewire::test(FooterDrivenWizardComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->html();

    expect($html)->toContain('select-create-back')
        ->and($html)->toContain('select-create-next')
        // The wizard's own row is gone, so the two navigations cannot both show.
        ->and($html)->not->toContain('wizard-next')
        ->and($html)->not->toContain('wizard-back')
        // Submit is seeded hidden on a multi-step wizard and revealed on the last step.
        ->and($html)->toContain('x-show="step >= total - 1"')
        // The footer is seeded with the real step count, so it is correct before
        // the first broadcast rather than after it.
        ->and($html)->toContain('total: 2');
});

test('the footer scopes its wizard events by name', function () {
    $create = Livewire::test(FooterDrivenWizardComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->html();

    $edit = Livewire::test(FooterDrivenWizardComponent::class)
        ->call('mountEditOption', 'data.category')
        ->html();

    expect($create)->toContain("wizard: 'opt'")
        ->and($edit)->toContain("wizard: 'editopt'");
});

test('a wizard that kept its navigation leaves the footer alone', function () {
    $html = Livewire::test(OptionFormHostComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->html();

    expect($html)->toContain('wizard-next')
        ->and($html)->not->toContain('select-create-next')
        ->and($html)->not->toContain('select-create-back');
});

test('an option form without a wizard keeps the plain footer', function () {
    $html = Livewire::test(CreateAndEditOptionSelectComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->html();

    expect($html)->not->toContain('select-create-next')
        ->and($html)->not->toContain('wire-wizard-navigate')
        ->and($html)->toContain('select-create-save');
});

test('the footer still submits through the same endpoint on the last step', function () {
    Livewire::test(FooterDrivenWizardComponent::class)
        ->call('mountCreateOption', 'data.category')
        ->set('createOptionFormData.name', 'Sport')
        ->call('createSelectOption')
        ->assertSet('mountedCreateOptionSelect', null)
        ->assertSet('data.category', 'c2');
});
