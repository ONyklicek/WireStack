<?php

declare(strict_types=1);

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
