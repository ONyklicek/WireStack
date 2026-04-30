<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use NyonCode\WireCore\WireCoreServiceProvider;
use NyonCode\WireForms\Components\Checkbox;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Components\Toggle;
use NyonCode\WireForms\Forms\Form;
use NyonCode\WireForms\WireFormsServiceProvider;

// ─── Service provider integration ───────────────────────────────

test('core + forms providers boot together', function () {
    $providers = app()->getLoadedProviders();

    expect($providers)
        ->toHaveKey(WireCoreServiceProvider::class)
        ->toHaveKey(WireFormsServiceProvider::class);
});

test('forms config is published alongside core config', function () {
    expect(config('wire-core'))->toBeArray()
        ->and(config('wire-forms'))->toBeArray();
});

// ─── Standalone Form (no Livewire) ─────────────────────────────

test('Form::make() creates instance without Livewire', function () {
    $form = Form::make();

    expect($form)->toBeInstanceOf(Form::class);
});

test('standalone form validates required fields', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email'])->required(),
        ])
        ->state(['name' => 'John', 'email' => 'john@example.com']);

    $data = $form->validate();

    expect($data)
        ->toHaveKey('name', 'John')
        ->toHaveKey('email', 'john@example.com');
});

test('standalone form throws ValidationException on invalid data', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email'])->required(),
        ])
        ->state(['name' => '', 'email' => 'not-an-email']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

test('standalone form with layout components validates correctly', function () {
    $form = Form::make()
        ->schema([
            Section::make('Personal')->schema([
                TextInput::make('first_name')->required(),
                TextInput::make('last_name')->required(),
            ]),
            Grid::make()->columns(2)->schema([
                TextInput::make('city'),
                TextInput::make('zip'),
            ]),
        ])
        ->state([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'city' => 'Prague',
            'zip' => '11000',
        ]);

    $data = $form->validate();

    expect($data)
        ->toHaveKey('first_name', 'John')
        ->toHaveKey('last_name', 'Doe')
        ->toHaveKey('city', 'Prague')
        ->toHaveKey('zip', '11000');
});

test('standalone form with multiple field types', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            Select::make('role')->options([
                'admin' => 'Admin',
                'user' => 'User',
            ])->required(),
            Checkbox::make('agree_terms')->required(),
            Toggle::make('newsletter'),
        ])
        ->state([
            'name' => 'Jane',
            'role' => 'admin',
            'agree_terms' => true,
            'newsletter' => false,
        ]);

    $data = $form->validate();

    expect($data)
        ->toHaveKey('name', 'Jane')
        ->toHaveKey('role', 'admin')
        ->toHaveKey('agree_terms', true)
        ->toHaveKey('newsletter', false);
});

test('standalone form statePath prefixes validation rules', function () {
    $form = Form::make()
        ->statePath('data')
        ->schema([
            TextInput::make('name')->required(),
        ]);

    $rules = $form->getValidationRules();

    expect($rules)->toHaveKey('data.name')
        ->and($rules)->not->toHaveKey('name');
});

test('standalone form fill and getState roundtrip', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name'),
            TextInput::make('email'),
        ]);

    $form->fill(['name' => 'Test', 'email' => 'test@example.com']);

    expect($form->getState())
        ->toBe(['name' => 'Test', 'email' => 'test@example.com']);
});

test('standalone form model detection', function () {
    $createForm = Form::make()->model('App\\Models\\User');
    $editForm = Form::make()->model(Mockery::mock(\Illuminate\Database\Eloquent\Model::class));
    $noModelForm = Form::make();

    expect($createForm->isCreating())->toBeTrue()
        ->and($createForm->isEditing())->toBeFalse()
        ->and($editForm->isEditing())->toBeTrue()
        ->and($editForm->isCreating())->toBeFalse()
        ->and($noModelForm->getModel())->toBeNull();
});

test('standalone form implements Htmlable', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')]);

    expect($form)->toBeInstanceOf(\Illuminate\Contracts\Support\Htmlable::class);
});

test('standalone form disabled propagates to fields', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name'),
            Select::make('role')->options(['a' => 'A']),
        ])
        ->disabled();

    $flat = $form->getFlatComponents();

    expect($flat[0]->isDisabled())->toBeTrue()
        ->and($flat[1]->isDisabled())->toBeTrue();
});

test('standalone form getFlatComponents flattens nested layouts', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('a'),
            Section::make('Group')->schema([
                TextInput::make('b'),
                Grid::make()->columns(2)->schema([
                    TextInput::make('c'),
                    TextInput::make('d'),
                ]),
            ]),
            TextInput::make('e'),
        ]);

    $flat = $form->getFlatComponents();

    expect($flat)->toHaveCount(5)
        ->and(array_map(fn ($c) => $c->getName(), $flat))
        ->toBe(['a', 'b', 'c', 'd', 'e']);
});

// ─── No WireTable dependency ────────────────────────────────────

test('forms package has no wire-table imports', function () {
    $formsDir = dirname(__DIR__, 2).'/packages/forms/src';

    if (! is_dir($formsDir)) {
        $this->markTestSkipped('packages/forms/src not found in test context');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($formsDir)
    );

    $violations = [];

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        if (str_contains($content, 'NyonCode\\WireTable')) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Forms package must not import from WireTable. Violations: '.implode(', ', $violations)
    );
});
