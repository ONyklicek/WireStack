<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ModalStep;

it('can be created via make()', function () {
    $step = ModalStep::make('details');

    expect($step->getLabel())->toBe('details');
});

it('can set description', function () {
    $step = ModalStep::make('details')->description('Fill in the details');

    expect($step->getDescription())->toBe('Fill in the details');
});

it('can set icon', function () {
    $step = ModalStep::make('details')->icon('user');

    expect($step->getIcon())->toBe('user');
});

it('can set schema as array', function () {
    $schema = [
        ['name' => 'name', 'type' => 'text'],
        ['name' => 'email', 'type' => 'email'],
    ];

    $step = ModalStep::make('details')->schema($schema);

    expect($step->getSchema())->toBe($schema);
});

it('supports dynamic schema via closure', function () {
    $step = ModalStep::make('details')
        ->schema(fn ($record) => [
            ['name' => 'name', 'type' => 'text', 'default' => $record->name],
        ]);

    $record = (object) ['name' => 'Jan'];
    expect($step->getSchema($record))->toHaveCount(1);
});

it('can set validation rules', function () {
    $step = ModalStep::make('details')
        ->validation(['name' => 'required']);

    expect($step->getValidation())->toHaveKey('name');
});

it('can set validation messages', function () {
    $step = ModalStep::make('details')
        ->validationMessages(['name.required' => 'Jméno je povinné']);

    expect($step->getValidationMessages())->toHaveKey('name.required');
});

it('can set afterValidation callback', function () {
    $callback = fn () => null;
    $step = ModalStep::make('details')->afterValidation($callback);

    expect($step->getAfterValidationCallback())->toBe($callback);
});

it('can set before callback', function () {
    $callback = fn () => null;
    $step = ModalStep::make('details')->before($callback);

    expect($step->getBeforeCallback())->toBe($callback);
});

it('serializes to array', function () {
    $step = ModalStep::make('Details')
        ->description('Enter details')
        ->icon('user')
        ->schema([['name' => 'name', 'type' => 'text']]);

    $array = $step->toArray();

    expect($array)->toBeArray()
        ->and($array['label'])->toBe('Details')
        ->and($array['description'])->toBe('Enter details')
        ->and($array['icon'])->toBe('user');
});
