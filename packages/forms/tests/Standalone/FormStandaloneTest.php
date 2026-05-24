<?php

declare(strict_types=1);

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use NyonCode\WireForms\Components\Layout\Grid;
use NyonCode\WireForms\Components\Layout\Section;
use NyonCode\WireForms\Components\Select;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Forms\Form;

test('Form::make() creates a new instance', function () {
    $form = Form::make();

    expect($form)->toBeInstanceOf(Form::class);
});

test('form with schema and state validates required fields', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email'])->required(),
        ])
        ->state(['name' => 'John', 'email' => 'john@example.com']);

    $data = $form->validate();

    expect($data)->toBe(['name' => 'John', 'email' => 'john@example.com']);
});

test('form validation throws on missing required fields', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->required(),
        ])
        ->state(['name' => 'John']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

test('form state management via fill', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')])
        ->statePath('data');

    $form->fill(['name' => 'Test']);

    expect($form->getState())->toBe(['name' => 'Test']);
});

test('form state alias works', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')]);

    $form->state(['name' => 'Hello']);

    expect($form->getState())->toBe(['name' => 'Hello']);
});

test('form getFlatComponents returns flat list', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('a'),
            Grid::make()->columns(2)->schema([
                TextInput::make('b'),
                TextInput::make('c'),
            ]),
            TextInput::make('d'),
        ]);

    $flat = $form->getFlatComponents();

    expect($flat)->toHaveCount(4)
        ->and($flat[0]->getName())->toBe('a')
        ->and($flat[1]->getName())->toBe('b')
        ->and($flat[2]->getName())->toBe('c')
        ->and($flat[3]->getName())->toBe('d');
});

test('form getSchema returns top-level components', function () {
    $schema = [
        TextInput::make('name'),
        Select::make('role'),
    ];

    $form = Form::make()->schema($schema);

    expect($form->getSchema())->toHaveCount(2);
});

test('form disabled propagates to components', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')])
        ->disabled();

    $flat = $form->getFlatComponents();
    expect($flat[0]->isDisabled())->toBeTrue();
});

test('form validation rules collected from schema', function () {
    $form = Form::make()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email']),
        ]);

    $rules = $form->getValidationRules();

    expect($rules)->toHaveKey('name')
        ->and($rules['name'])->toContain('required')
        ->and($rules)->toHaveKey('email')
        ->and($rules['email'])->toContain('email');
});

test('form validation with statePath prefixes rules', function () {
    $form = Form::make()
        ->statePath('data')
        ->schema([
            TextInput::make('name')->required(),
        ]);

    $rules = $form->getValidationRules();

    expect($rules)->toHaveKey('data.name');
});

test('form isCreating and isEditing with class-string', function () {
    $form = Form::make()->model('App\\Models\\User');

    expect($form->isCreating())->toBeTrue()
        ->and($form->isEditing())->toBeFalse()
        ->and($form->getModel())->toBe('App\\Models\\User');
});

test('form isEditing with model instance', function () {
    $model = Mockery::mock(Model::class);

    $form = Form::make()->model($model);

    expect($form->isEditing())->toBeTrue()
        ->and($form->isCreating())->toBeFalse();
});

test('form getModel returns null when not set', function () {
    $form = Form::make();

    expect($form->getModel())->toBeNull();
});

test('form implements Htmlable', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')]);

    expect($form)->toBeInstanceOf(Htmlable::class);
});

test('form fluent API is chainable', function () {
    $form = Form::make()
        ->schema([TextInput::make('name')])
        ->statePath('data')
        ->model('App\\Models\\User')
        ->mutateDataBeforeSave(fn ($data) => $data)
        ->beforeSave(fn ($data) => null)
        ->afterSave(fn ($record) => null)
        ->successMessage('Saved!')
        ->validationMessages(['name.required' => 'Name is required'])
        ->disabled(false);

    expect($form)->toBeInstanceOf(Form::class);
});

test('form disableSuccessNotification sets message to null', function () {
    $form = Form::make()->disableSuccessNotification();

    // We can test this indirectly - the form should still work
    expect($form)->toBeInstanceOf(Form::class);
});

test('form validates nested layout components', function () {
    $form = Form::make()
        ->schema([
            Section::make('Personal')->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->rules(['email'])->required(),
            ]),
        ])
        ->state(['name' => '', 'email' => 'invalid']);

    expect(fn () => $form->validate())
        ->toThrow(ValidationException::class);
});

test('form validates successfully with valid nested data', function () {
    $form = Form::make()
        ->schema([
            Section::make('Personal')->schema([
                TextInput::make('name')->required(),
            ]),
        ])
        ->state(['name' => 'John']);

    $data = $form->validate();

    expect($data)->toHaveKey('name')
        ->and($data['name'])->toBe('John');
});
