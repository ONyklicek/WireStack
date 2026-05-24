<?php

declare(strict_types=1);

use Livewire\Component;
use Livewire\Livewire;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\Forms\WithForms;

// ─── Test components ──────────────────────────────────────────

class SingleFormComponent extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->rules(['email'])->required(),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->form }}</div>';
    }
}

class MultiFormComponent extends Component
{
    use WithForms;

    public ?array $profileData = [];

    public ?array $settingsData = [];

    public function profileForm(Form $form): Form
    {
        return $form
            ->statePath('profileData')
            ->schema([
                TextInput::make('name')->required(),
            ]);
    }

    public function settingsForm(Form $form): Form
    {
        return $form
            ->statePath('settingsData')
            ->schema([
                Toggle::make('notifications'),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class ExplicitFormsComponent extends Component
{
    use WithForms;

    public ?array $data = [];

    public function myCustomMethod(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('name'),
            ]);
    }

    protected function getForms(): array
    {
        return ['myCustomMethod'];
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class CoexistenceViolationComponent extends Component
{
    use WithForms;

    public ?array $data = [];

    public function form(Form $form): Form
    {
        return $form->schema([TextInput::make('a')]);
    }

    public function profileForm(Form $form): Form
    {
        return $form->schema([TextInput::make('b')]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class NoFormsComponent extends Component
{
    use WithForms;

    public function render(): string
    {
        return '<div></div>';
    }
}

class RepeaterActionsComponent extends Component
{
    use WithForms;

    public array $data = [
        'contacts' => [
            ['name' => 'Alpha'],
            ['name' => 'Beta'],
            ['name' => 'Gamma'],
        ],
    ];

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Repeater::make('contacts')
                    ->schema([
                        TextInput::make('name'),
                    ])
                    ->reorderable(),
            ]);
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

// ─── Single form tests ───────────────────────────────────────

test('single form component resolves form via magic __get', function () {
    Livewire::test(SingleFormComponent::class)
        ->assertOk();
});

test('single form auto-detects form() method', function () {
    $component = new SingleFormComponent;
    $component->bootWithForms();

    $forms = (new ReflectionMethod($component, 'getForms'))->invoke($component);

    expect($forms)->toBe(['form']);
});

// ─── Multi-form tests ─────────────────────────────────────────

test('multi-form component resolves multiple forms', function () {
    Livewire::test(MultiFormComponent::class)
        ->assertOk();
});

test('multi-form auto-detects *Form methods', function () {
    $component = new MultiFormComponent;

    $forms = (new ReflectionMethod($component, 'getForms'))->invoke($component);

    expect($forms)->toContain('profileForm')
        ->and($forms)->toContain('settingsForm');
});

// ─── Explicit forms registration ──────────────────────────────

test('explicit getForms overrides auto-detect', function () {
    $component = new ExplicitFormsComponent;

    $forms = (new ReflectionMethod($component, 'getForms'))->invoke($component);

    expect($forms)->toBe(['myCustomMethod']);
});

// ─── Coexistence validation ───────────────────────────────────

test('mixing form() and *Form() throws InvalidArgumentException', function () {
    expect(function () {
        $component = new CoexistenceViolationComponent;
        $component->bootWithForms();
    })->toThrow(
        InvalidArgumentException::class,
        'cannot have both form() and profileForm()'
    );
});

// ─── Form caching ─────────────────────────────────────────────

test('form is cached after first resolution', function () {
    Livewire::test(SingleFormComponent::class)
        ->assertOk();

    // The caching is internal, but we verify the component works without errors
    // on repeated access (Livewire re-renders trigger __get multiple times)
});

// ─── No forms component ──────────────────────────────────────

test('component with WithForms but no form methods works', function () {
    $component = new NoFormsComponent;
    $component->bootWithForms();

    $forms = (new ReflectionMethod($component, 'getForms'))->invoke($component);

    expect($forms)->toBe([]);
});

// ─── Repeater actions ─────────────────────────────────────────────

test('repeater actions add remove and reorder nested items', function () {
    $component = Livewire::test(RepeaterActionsComponent::class);

    $component->call('addRepeaterItem', 'data.contacts');

    expect($component->get('data')['contacts'])->toBe([
        ['name' => 'Alpha'],
        ['name' => 'Beta'],
        ['name' => 'Gamma'],
        [],
    ]);

    $component->call('removeRepeaterItem', 'data.contacts', 1);

    expect($component->get('data')['contacts'])->toBe([
        ['name' => 'Alpha'],
        ['name' => 'Gamma'],
        [],
    ]);

    $component->call('reorderRepeaterItems', 'data.contacts', [2, 0]);

    expect($component->get('data')['contacts'])->toBe([
        [],
        ['name' => 'Alpha'],
    ]);
});

test('repeater actions ignore non array state', function () {
    $component = Livewire::test(RepeaterActionsComponent::class)
        ->set('data.contacts', 'not-array');

    $component->call('removeRepeaterItem', 'data.contacts', 0)
        ->call('reorderRepeaterItems', 'data.contacts', [0]);

    expect($component->get('data')['contacts'])->toBe('not-array');
});
