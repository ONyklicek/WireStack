<?php

declare(strict_types=1);

use NyonCode\WireCore\Actions\ModalStep;
use NyonCode\WireForms\Components\TextInput;

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

it('can set schema with components', function () {
    $schema = [
        TextInput::make('name'),
        TextInput::make('email'),
    ];

    $step = ModalStep::make('details')->schema($schema);

    expect($step->getSchema())->toHaveCount(2);
});

it('supports dynamic schema via closure', function () {
    $step = ModalStep::make('details')
        ->schema(fn ($record) => [
            TextInput::make('name'),
        ]);

    $record = (object) ['name' => 'Jan'];
    expect($step->getSchema($record))->toHaveCount(1);
});

it('builds a closure schema from an empty data bag (recordless wizard first step)', function () {
    // Regression: a header-action wizard seeds its first step from an empty data
    // bag; `&& $context` treated [] as falsy, so the closure was skipped and the
    // first step rendered no fields. An empty array must still evaluate; only a
    // literal null (wizard chrome) falls back to the base schema.
    $step = ModalStep::make('details')
        ->schema(fn (array $data) => [
            TextInput::make('name'),
            TextInput::make('email'),
        ]);

    expect($step->getSchema([]))->toHaveCount(2)
        ->and($step->getSchema())->toHaveCount(0); // null context → base schema
});

it('resolves a closure validation from an empty data bag', function () {
    $step = ModalStep::make('details')
        ->validation(fn (array $data) => ['name' => 'required']);

    expect($step->getValidation([]))->toHaveKey('name')
        ->and($step->getValidation())->toBe([]); // null context → base
});

// ─── Context equivalence matrix (record / empty bag / null) × (static / closure)

it('resolves a closure step schema across every context kind', function (mixed $context, int $expected) {
    $step = ModalStep::make('details')->schema(fn ($data) => [
        TextInput::make('a'),
        TextInput::make('b'),
    ]);

    expect($step->getSchema($context))->toHaveCount($expected);
})->with([
    'record object (row action)' => [(object) ['name' => 'X'], 2],
    'empty data bag (recordless first step)' => [[], 2],
    'non-empty data bag (later step)' => [['name' => 'X'], 2],
    'null (wizard chrome)' => [null, 0],
]);

it('returns the static step schema regardless of context', function (mixed $context) {
    $step = ModalStep::make('details')->schema([TextInput::make('a')]);

    expect($step->getSchema($context))->toHaveCount(1);
})->with([
    'record object' => [(object) ['x' => 1]],
    'empty data bag' => [[]],
    'null' => [null],
]);

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
        ->schema([TextInput::make('name')]);

    $array = $step->toArray();

    expect($array)->toBeArray()
        ->and($array['label'])->toBe('Details')
        ->and($array['description'])->toBe('Enter details')
        ->and($array['icon'])->toBe('user');
});
