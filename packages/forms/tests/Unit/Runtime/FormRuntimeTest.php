<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Livewire\Component;
use NyonCode\WireForms\Components\CheckboxList;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Forms\Runtime\FormRuntime;
use NyonCode\WireForms\Forms\Runtime\StateManager;

enum FormRuntimeStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}

test('validate returns validated data on success', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name')->required(),
        ],
    );

    $stateManager = new StateManager;
    $stateManager->fill(['name' => 'John']);

    $runtime = new FormRuntime($config, $stateManager);
    $data = $runtime->validate();

    expect($data)->toBe(['name' => 'John']);
});

test('validate throws ValidationException on failure', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name')->required(),
            TextInput::make('email')->required(),
        ],
    );

    $stateManager = new StateManager;
    $stateManager->fill(['name' => 'John']);

    $runtime = new FormRuntime($config, $stateManager);

    expect(fn () => $runtime->validate())
        ->toThrow(ValidationException::class);
});

test('validate uses statePath to prefix rules', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name')->required(),
        ],
        statePath: 'data',
    );

    $stateManager = new StateManager;
    $stateManager->setStatePath('data');
    $stateManager->fill(['name' => 'John']);

    $runtime = new FormRuntime($config, $stateManager);
    $data = $runtime->validate();

    expect($data)->toHaveKey('data.name');
});

test('validate merges form-level validation messages', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name')->required(),
        ],
        validationMessages: ['name.required' => 'Custom required message'],
    );

    $stateManager = new StateManager;
    $stateManager->fill([]);

    $runtime = new FormRuntime($config, $stateManager);

    try {
        $runtime->validate();
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors()['name'][0])->toBe('Custom required message');
    }
});

test('getState delegates to StateManager', function () {
    $config = new FormConfig;

    $stateManager = new StateManager;
    $stateManager->fill(['foo' => 'bar']);

    $runtime = new FormRuntime($config, $stateManager);

    expect($runtime->getState())->toBe(['foo' => 'bar']);
});

test('fill delegates to StateManager', function () {
    $config = new FormConfig;

    $stateManager = new StateManager;

    $runtime = new FormRuntime($config, $stateManager);
    $runtime->fill(['key' => 'value']);

    expect($runtime->getState())->toBe(['key' => 'value']);
});

test('getStateManager returns the injected StateManager', function () {
    $config = new FormConfig;
    $stateManager = new StateManager;

    $runtime = new FormRuntime($config, $stateManager);

    expect($runtime->getStateManager())->toBe($stateManager);
});

test('getFlatComponents flattens nested layouts', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('a'),
            Grid::make()->columns(2)->schema([
                TextInput::make('b'),
                TextInput::make('c'),
            ]),
            TextInput::make('d'),
        ],
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);

    $flat = $runtime->getFlatComponents();

    expect($flat)->toHaveCount(4)
        ->and($flat[0]->getName())->toBe('a')
        ->and($flat[1]->getName())->toBe('b')
        ->and($flat[2]->getName())->toBe('c')
        ->and($flat[3]->getName())->toBe('d');
});

test('getFlatComponents flattens deeply nested layouts', function () {
    $config = new FormConfig(
        schema: [
            Section::make('outer')->schema([
                Grid::make()->columns(2)->schema([
                    TextInput::make('deep'),
                ]),
            ]),
        ],
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);

    $flat = $runtime->getFlatComponents();

    expect($flat)->toHaveCount(1)
        ->and($flat[0]->getName())->toBe('deep');
});

test('getFlatComponents is cached', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('a'),
            TextInput::make('b'),
        ],
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);

    $first = $runtime->getFlatComponents();
    $second = $runtime->getFlatComponents();

    expect($first)->toBe($second);
});

test('prepare sets statePath on components', function () {
    $input = TextInput::make('name');

    $config = new FormConfig(
        schema: [$input],
        statePath: 'data',
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $runtime->prepare();

    expect($input->getStatePath())->toBe('data.name');
});

test('prepare propagates disabled state to components', function () {
    $input = TextInput::make('name');

    $config = new FormConfig(
        schema: [$input],
        isDisabled: true,
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);
    $runtime->prepare();

    expect($input->isDisabled())->toBeTrue();
});

test('prepare propagates the livewire instance to fields', function () {
    $input = TextInput::make('name');
    $nested = TextInput::make('city');

    $config = new FormConfig(
        schema: [
            $input,
            Section::make('addr')->schema([$nested]),
        ],
        statePath: 'data',
    );

    $livewire = new class extends Component
    {
        public array $data = [];

        public function render(): string
        {
            return '<div></div>';
        }
    };

    $stateManager = new StateManager;
    $stateManager->setLivewire($livewire);

    $runtime = new FormRuntime($config, $stateManager);
    $runtime->prepare();

    expect($input->getLivewire())->toBe($livewire)
        ->and($nested->getLivewire())->toBe($livewire);
});

test('prepare leaves livewire null when none is bound', function () {
    $input = TextInput::make('name');

    $config = new FormConfig(schema: [$input], statePath: 'data');

    $runtime = new FormRuntime($config, new StateManager);
    $runtime->prepare();

    expect($input->getLivewire())->toBeNull();
});

test('prepare is idempotent', function () {
    $input = TextInput::make('name');

    $config = new FormConfig(
        schema: [$input],
        statePath: 'data',
    );

    $stateManager = new StateManager;
    $runtime = new FormRuntime($config, $stateManager);

    $runtime->prepare();
    $runtime->prepare(); // second call should be no-op

    expect($input->getStatePath())->toBe('data.name');
});

test('save delegates to SaveHandler', function () {
    $config = new FormConfig(
        schema: [
            TextInput::make('name'),
        ],
        using: fn (array $data) => $data,
    );

    $stateManager = new StateManager;
    $stateManager->fill(['name' => 'John']);

    $runtime = new FormRuntime($config, $stateManager);
    $result = $runtime->save();

    expect($result)->toBe(['name' => 'John']);
});

// ─── Initial state seeding ───────────────────────────────────────────────────

function seedingRuntime(array $schema): FormRuntime
{
    return new FormRuntime(new FormConfig(schema: $schema), new StateManager);
}

test('getInitialState seeds field defaults, type-correct blanks and array fields', function () {
    $runtime = seedingRuntime([
        TextInput::make('title')->default('Untitled'),
        TextInput::make('subtitle'),
        Toggle::make('active'),
        CheckboxList::make('permissions')->options(['a' => 'A']),
    ]);

    expect($runtime->getInitialState())->toBe([
        'title' => 'Untitled',
        'subtitle' => null,
        'active' => false,
        'permissions' => [],
    ]);
});

test('getInitialState reduces enum defaults to their scalar key', function () {
    $runtime = seedingRuntime([
        Select::make('status')
            ->options(FormRuntimeStatus::class)
            ->default(FormRuntimeStatus::Draft),
        Repeater::make('rows')
            ->schema([TextInput::make('label')])
            ->default([['label' => 'a', 'status' => FormRuntimeStatus::Published]]),
    ]);

    // The seed feeds Livewire state directly; an enum instance there cannot
    // round-trip to the browser and would never match an <option> value.
    expect($runtime->getInitialState())->toBe([
        'status' => 'draft',
        'rows' => [['label' => 'a', 'status' => 'published']],
    ]);
});

test('getInitialState descends into layouts and seeds top-level repeaters', function () {
    $runtime = seedingRuntime([
        Section::make('Details')->schema([
            TextInput::make('name'),
        ]),
        Repeater::make('rows')->schema([
            TextInput::make('label'),
        ]),
        Repeater::make('contacts')
            ->schema([TextInput::make('email')])
            ->default([['email' => 'a@b.c']]),
    ]);

    // A repeater seeds its own key — its ->default() rows when set, otherwise an
    // empty array — never its per-item child at the template path.
    expect($runtime->getInitialState())->toEqual([
        'name' => null,
        'rows' => [],
        'contacts' => [['email' => 'a@b.c']],
    ]);
});

test('fill seeds every schema key the caller omitted (create mode)', function () {
    $runtime = seedingRuntime([
        TextInput::make('title')->default('Untitled'),
        CheckboxList::make('permissions')->options(['a' => 'A']),
    ]);

    $runtime->fill([]);

    expect($runtime->getState())->toBe([
        'title' => 'Untitled',
        'permissions' => [],
    ]);
});

test('fill never overwrites an intentional null with a field default (edit mode)', function () {
    $runtime = seedingRuntime([
        TextInput::make('title')->default('Untitled'),
    ]);

    // The record supplies the key as null on purpose — the default must not
    // resurrect a value the caller cleared.
    $runtime->fill(['title' => null]);

    expect($runtime->getState())->toBe(['title' => null]);
});

test('defaultOnNull fills an edit-mode null, scoped to the opted-in field', function () {
    $runtime = seedingRuntime([
        // Opts in: an edit-mode null is treated as unset and takes the default.
        TextInput::make('title')->default('Untitled')->defaultOnNull(),
        // Enum default still scalarises through the same path.
        Select::make('status')->options(FormRuntimeStatus::class)->default(FormRuntimeStatus::Draft)->defaultOnNull(),
        // Empty string counts as empty for an opted-in field too.
        TextInput::make('code')->default('AUTO')->defaultOnNull(),
        // Does NOT opt in: its null must be preserved.
        TextInput::make('note')->default('n/a'),
    ]);

    $runtime->fill(['title' => null, 'status' => null, 'code' => '', 'note' => null]);

    expect($runtime->getState())->toBe([
        'title' => 'Untitled',
        'status' => 'draft',
        'code' => 'AUTO',
        'note' => null,
    ]);
});

test('defaultOnNull leaves a real edit-mode value untouched', function () {
    $runtime = seedingRuntime([
        TextInput::make('title')->default('Untitled')->defaultOnNull(),
    ]);

    $runtime->fill(['title' => 'Real']);

    expect($runtime->getState())->toBe(['title' => 'Real']);
});

test('fill keeps caller values over defaults and blanks', function () {
    $runtime = seedingRuntime([
        TextInput::make('title')->default('Untitled'),
        CheckboxList::make('permissions')->options(['a' => 'A', 'b' => 'B']),
    ]);

    $runtime->fill(['title' => 'Real', 'permissions' => ['a']]);

    expect($runtime->getState())->toBe([
        'title' => 'Real',
        'permissions' => ['a'],
    ]);
});
