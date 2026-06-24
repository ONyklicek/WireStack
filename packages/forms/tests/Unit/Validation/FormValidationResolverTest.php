<?php

declare(strict_types=1);

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use NyonCode\WireCore\Core\Validation\ValidationResult;
use NyonCode\WireForms\Components\Repeater;
use NyonCode\WireForms\Components\TextInput;
use NyonCode\WireForms\Validation\FormValidationResolver;

test('collects rules from fields', function () {
    $fields = [
        TextInput::make('name')->required(),
        TextInput::make('email')->rules(['email', 'max:255']),
    ];

    $resolver = new FormValidationResolver($fields, 'data');
    $rules = $resolver->getRules();

    expect($rules)->toHaveKey('data.name')
        ->and($rules['data.name'])->toContain('required')
        ->and($rules)->toHaveKey('data.email')
        ->and($rules['data.email'])->toContain('email')
        ->and($rules['data.email'])->toContain('max:255');
});

test('collects rules without statePath', function () {
    $fields = [
        TextInput::make('name')->required(),
    ];

    $resolver = new FormValidationResolver($fields);
    $rules = $resolver->getRules();

    expect($rules)->toHaveKey('name')
        ->and($rules['name'])->toContain('required');
});

test('collects validation attributes from field labels', function () {
    $fields = [
        TextInput::make('first_name')->label('First Name')->required(),
    ];

    $resolver = new FormValidationResolver($fields, 'data');
    $attributes = $resolver->getAttributes();

    expect($attributes)->toHaveKey('data.first_name')
        ->and($attributes['data.first_name'])->toBe('First Name');
});

test('merges form-level validation messages', function () {
    $fields = [
        TextInput::make('name')->required()->validationMessages(['required' => 'Field required']),
    ];

    $formMessages = ['data.email.required' => 'Email is required'];
    $resolver = new FormValidationResolver($fields, 'data', $formMessages);
    $messages = $resolver->getMessages();

    expect($messages)->toHaveKey('data.email.required')
        ->and($messages)->toHaveKey('data.name.required')
        ->and($messages['data.name.required'])->toBe('Field required');
});

test('skips components without validation interface', function () {
    // ViewComponent doesn't implement HasValidation
    $resolver = new FormValidationResolver([], null);

    expect($resolver->getRules())->toBe([])
        ->and($resolver->getMessages())->toBe([])
        ->and($resolver->getAttributes())->toBe([]);
});

// ─── Repeater wildcard validation (regression) ─────────────────────────────

test('emits container and per-item wildcard rules for repeaters', function () {
    $repeater = Repeater::make('contacts')
        ->minItems(1)
        ->required()
        ->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->rules(['email']),
        ]);

    // Resolve the repeater's prefixed state path the way the runtime does.
    $repeater->prepareChildren('data');

    $resolver = new FormValidationResolver([], 'data', [], [$repeater]);
    $rules = $resolver->getRules();

    expect($rules)->toHaveKey('data.contacts')
        ->and($rules['data.contacts'])->toContain('required')
        ->and($rules['data.contacts'])->toContain('array')
        ->and($rules['data.contacts'])->toContain('min:1')
        ->and($rules)->toHaveKey('data.contacts.*.name')
        ->and($rules['data.contacts.*.name'])->toContain('required')
        ->and($rules)->toHaveKey('data.contacts.*.email')
        ->and($rules['data.contacts.*.email'])->toContain('email')
        ->and($rules)->not->toHaveKey('data.name');
});

// ─── Core ValidationPipeline Integration (Phase 4) ─────────────────────────

test('validateUsing delegates to Core ValidationPipeline and returns ValidationResult', function () {
    // Register the Validator Factory in the container (required for ValidationPipeline)
    app()->singleton(
        Illuminate\Contracts\Validation\Factory::class,
        fn ($app) => new Factory(
            new Translator(
                new ArrayLoader, 'en'
            ),
            $app,
        ),
    );

    $fields = [
        TextInput::make('name')->required(),
        TextInput::make('email')->rules(['email']),
    ];

    $resolver = new FormValidationResolver($fields, 'data');

    $result = $resolver->validateUsing(['data' => ['name' => 'Alice', 'email' => 'alice@test.com']]);
    expect($result)->toBeInstanceOf(ValidationResult::class)
        ->and($result->passed())->toBeTrue()
        ->and($result->errors())->toBeEmpty();
});

test('validateUsing returns failure for invalid data', function () {
    // Register the Validator Factory in the container
    app()->singleton(
        Illuminate\Contracts\Validation\Factory::class,
        fn ($app) => new Factory(
            new Translator(
                new ArrayLoader, 'en'
            ),
            $app,
        ),
    );

    $fields = [
        TextInput::make('name')->required(),
    ];

    $resolver = new FormValidationResolver($fields, 'data');

    $result = $resolver->validateUsing(['data' => ['name' => '']]);
    expect($result)->toBeInstanceOf(ValidationResult::class)
        ->and($result->failed())->toBeTrue()
        ->and($result->errors())->not->toBeEmpty()
        ->and($result->hasError('data.name'))->toBeTrue();
});
